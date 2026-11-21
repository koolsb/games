/*
 * Alpine glue for the scoreboard: the setup screen, the "follow a game"
 * box, and the play screen (scorekeeper or follower). The rules live in
 * model.js and the storage/transport in sync.js — this file only wires
 * them to the page.
 */
import * as model from './model';
import * as sync from './sync';

/* A tap should reach followers quickly, but a run of taps is one push. */
const PUSH_DEBOUNCE_MS = 300;

/* Followers poll; a hidden tab polls much less. */
const POLL_MS = 3000;
const POLL_HIDDEN_MS = 15000;

const CODE_PATTERN = /^[A-HJ-NP-Z2-7]{4}$/;

export function normalizeCode(code) {
    return String(code ?? '')
        .trim()
        .toUpperCase()
        .replace(/[\s-]/g, '');
}

function playUrl(code = null) {
    return code ? `/scoreboard/play/${code}` : '/scoreboard/play';
}

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /* The new-game form and the list of games this device is scoring. */
    Alpine.data('scoreboardSetup', () => ({
        title: '',
        lowestWins: false,
        names: ['', ''],
        records: [],
        maxPlayers: model.MAX_PLAYERS,

        init() {
            const remembered = sync.rememberedNames();

            if (remembered.length) this.names = remembered.slice(0, model.MAX_PLAYERS);

            this.refresh();
        },

        refresh() {
            this.records = sync.hostedRecords().map((record) => ({
                id: record.id,
                code: record.code,
                title: record.game.title,
                players: record.game.players.map((p) => p.name).join(', '),
                round: record.game.round,
                savedAt: record.savedAt,
            }));
        },

        addName() {
            if (this.names.length >= model.MAX_PLAYERS) return;

            this.names.push('');
            this.$nextTick(() => [...this.$root.querySelectorAll('[data-name-input]')].at(-1)?.focus());
        },

        removeName(index) {
            if (this.names.length > 1) this.names.splice(index, 1);
        },

        moveName(index, offset) {
            const to = index + offset;

            if (to < 0 || to >= this.names.length) return;

            const [name] = this.names.splice(index, 1);

            this.names.splice(to, 0, name);
        },

        start() {
            const game = model.newGame({ title: this.title, lowestWins: this.lowestWins, names: this.names });
            const record = { id: model.uid(), code: null, token: null, game, syncedRev: 0 };

            sync.saveRecord(record);
            sync.setCurrent(record.id);
            sync.rememberNames(game.players.map((p) => p.name));

            window.location.href = playUrl();
        },

        open(record) {
            sync.setCurrent(record.id);
            window.location.href = playUrl(record.code);
        },

        forget(record) {
            sync.removeRecord(record.id);
            this.refresh();
        },

        ago(timestamp) {
            return timeAgo(timestamp);
        },
    }));

    /* "Someone else is keeping score" — check the code before leaving, so
     * a typo says so while the scorekeeper is still in earshot. */
    Alpine.data('scoreboardFollow', () => ({
        code: '',
        error: null,
        busy: false,

        async follow() {
            const code = normalizeCode(this.code);

            if (!CODE_PATTERN.test(code)) {
                this.error = 'A game code is 4 letters or numbers.';

                return;
            }

            if (sync.recordByCode(code)) {
                window.location.href = playUrl(code);

                return;
            }

            this.busy = true;
            this.error = null;

            try {
                await sync.fetchBoard(code);
                window.location.href = playUrl(code);
            } catch (error) {
                this.error =
                    error.status === 404
                        ? `There's no game running with the code ${code}.`
                        : sync.isTransient(error)
                          ? "Can't reach the server — check your connection and try again."
                          : error.message;
            } finally {
                this.busy = false;
            }
        },
    }));

    /*
     * The play screen. `code` is the one in the URL (or null for this
     * device's current game); `initial` is the server's snapshot, rendered
     * into the page so a follower sees scores on first paint.
     */
    Alpine.data('scoreboardGame', (code = null, initial = null) => ({
        mode: 'loading', // 'host' | 'viewer' | 'missing'
        record: null,
        board: null,
        fetchedAt: null,

        // Scorekeeper sync
        syncState: 'synced', // 'synced' | 'syncing' | 'offline' | 'error'
        syncMessage: '',
        failures: 0,
        pushing: false,
        pushAgain: false,
        pushTimer: null,
        retryTimer: null,

        // Follower polling
        viewerState: 'live', // 'live' | 'offline' | 'missing'
        pollTimer: null,

        // Layout
        pane: 'scores',
        wide: false,
        now: Date.now(),

        // Sheets
        sheet: { playerId: null, delta: 0, negative: false },
        roundScores: {},
        cell: { playerId: null, round: null, amount: '' },
        newPlayer: '',
        copied: false,
        copyFailed: false,

        init() {
            const media = window.matchMedia('(min-width: 1024px)');

            this.wide = media.matches;
            media.addEventListener('change', (e) => (this.wide = e.matches));
            setInterval(() => (this.now = Date.now()), 10000);

            const record = code ? sync.recordByCode(code) : sync.recordById(sync.currentId());

            if (record) {
                this.startHost(record);
            } else if (code) {
                this.startViewer(initial);
            } else {
                // No code and nothing on this device — back to setup.
                window.location.replace('/scoreboard');
            }
        },

        // -- scorekeeper -------------------------------------------------

        startHost(record) {
            this.mode = 'host';
            this.record = record;
            sync.setCurrent(record.id);
            this.syncUrl();

            window.addEventListener('online', () => this.flush());
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) this.flush();
            });

            this.flush();
        },

        /* Apply a model function, save locally at once, push soon. */
        apply(change) {
            const next = change(this.record.game);

            if (next === this.record.game) return;

            this.record = { ...this.record, game: next };
            sync.saveRecord(this.record);

            clearTimeout(this.pushTimer);
            this.pushTimer = setTimeout(() => this.flush(), PUSH_DEBOUNCE_MS);
        },

        async flush() {
            clearTimeout(this.retryTimer);

            if (this.mode !== 'host') return;

            if (this.pushing) {
                this.pushAgain = true;

                return;
            }

            if (!sync.isDirty(this.record)) {
                this.syncState = 'synced';

                return;
            }

            this.pushing = true;

            if (this.failures === 0) this.syncState = 'syncing';

            try {
                const patch = await sync.push(this.record);

                // Merge onto the latest record: taps made while the push was
                // in flight are newer than what was sent and must stay.
                this.record = {
                    ...this.record,
                    ...patch,
                    syncedRev: Math.max(patch.code === this.record.code ? (this.record.syncedRev ?? 0) : 0, patch.syncedRev),
                };
                sync.saveRecord(this.record);
                this.failures = 0;
                this.syncState = sync.isDirty(this.record) ? 'syncing' : 'synced';
                this.syncUrl();
            } catch (error) {
                this.failures += 1;
                this.syncState = sync.isTransient(error) ? 'offline' : 'error';
                this.syncMessage = error.message;
                this.retryTimer = setTimeout(() => this.flush(), Math.min(2000 * 2 ** (this.failures - 1), 30000));
            } finally {
                this.pushing = false;
            }

            if (this.pushAgain || (this.failures === 0 && sync.isDirty(this.record))) {
                this.pushAgain = false;
                this.flush();
            }
        },

        /* Once the game has a code, the URL carries it — so a refresh, a
         * bookmark or the share link all land on the same game. */
        syncUrl() {
            const want = playUrl(this.record?.code);

            if (window.location.pathname !== want) window.history.replaceState(null, '', want);
        },

        // -- follower ----------------------------------------------------

        startViewer(initialBoard) {
            this.mode = 'viewer';

            const cached = sync.cachedBoard(code);

            if (initialBoard) {
                this.board = initialBoard;
                this.fetchedAt = Date.now();
                sync.cacheBoard(code, initialBoard);
            } else if (cached) {
                this.board = cached.board;
                this.fetchedAt = cached.fetchedAt;
            }

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden) this.poll();
            });

            this.poll();
        },

        async poll() {
            clearTimeout(this.pollTimer);

            try {
                const { board } = await sync.fetchBoard(code);

                this.board = board;
                this.fetchedAt = Date.now();
                this.viewerState = 'live';
                sync.cacheBoard(code, board);
            } catch (error) {
                this.viewerState = error.status === 404 ? 'missing' : 'offline';
            }

            this.pollTimer = setTimeout(() => this.poll(), document.hidden ? POLL_HIDDEN_MS : POLL_MS);
        },

        // -- what both modes show ------------------------------------------

        get isHost() {
            return this.mode === 'host';
        },

        get game() {
            if (this.isHost) return this.record?.game ?? null;

            return model.isValidGame(this.board?.state) ? this.board.state : null;
        },

        get code() {
            return this.isHost ? this.record?.code : code;
        },

        get title() {
            return this.game?.title || 'Scoreboard';
        },

        get standings() {
            return this.game ? model.standings(this.game) : [];
        },

        get grid() {
            return this.game ? model.roundGrid(this.game) : [];
        },

        get totals() {
            return this.game ? model.totals(this.game) : {};
        },

        get shareUrl() {
            return this.code ? `${window.location.origin}${playUrl(this.code)}` : '';
        },

        get statusLabel() {
            if (this.isHost) {
                return {
                    synced: this.code ? 'Synced' : 'Saved',
                    syncing: 'Syncing…',
                    offline: 'Offline — saved here',
                    error: "Couldn't sync — saved here",
                }[this.syncState];
            }

            if (this.viewerState === 'live') return 'Live';
            if (this.viewerState === 'missing') return 'Game ended';

            return `Offline · ${timeAgo(this.fetchedAt, this.now)}`;
        },

        get statusTone() {
            const good = this.isHost ? this.syncState === 'synced' || this.syncState === 'syncing' : this.viewerState === 'live';

            return good ? 'ok' : 'warn';
        },

        get undoLabel() {
            const batch = this.game ? model.lastBatch(this.game) : null;

            if (!batch) return null;

            if (batch.kind === 'round') return `Undo round ${batch.round}`;

            const entry = batch.entries[0];

            return `Undo ${signed(entry.points)} for ${this.playerName(entry.playerId)}`;
        },

        get canStepBack() {
            return this.game ? model.canStepBack(this.game) : false;
        },

        playerName(id) {
            return this.game?.players.find((p) => p.id === id)?.name ?? '';
        },

        signed(n) {
            return signed(n);
        },

        ordinal(n) {
            return ordinal(n);
        },

        get lowestWinsSetting() {
            return this.game?.lowestWins ?? false;
        },

        set lowestWinsSetting(value) {
            this.setLowestWins(value);
        },

        // -- scorekeeper actions -----------------------------------------

        quick(playerId, points) {
            this.apply((game) => model.addPoints(game, playerId, points));
        },

        openAdd(playerId) {
            if (!this.isHost) return;

            this.sheet = { playerId, delta: 0, negative: false };
            this.$flux.modal('add-points').show();
            this.$nextTick(() => document.getElementById('add-points-amount')?.focus());
        },

        get amount() {
            return this.sheet.delta === 0 ? '' : String(Math.abs(this.sheet.delta));
        },

        set amount(value) {
            const n = Math.abs(parseInt(String(value).replace(/[^0-9]/g, ''), 10) || 0);

            this.sheet.delta = this.sheet.negative ? -n : n;
        },

        nudge(n) {
            this.sheet.delta += n;

            if (this.sheet.delta !== 0) this.sheet.negative = this.sheet.delta < 0;
        },

        flipSign() {
            this.sheet.negative = !this.sheet.negative;
            this.sheet.delta = -this.sheet.delta;
        },

        saveAdd() {
            if (this.sheet.delta !== 0) this.quick(this.sheet.playerId, this.sheet.delta);

            this.$flux.modal('add-points').close();
        },

        openRound() {
            this.roundScores = Object.fromEntries(this.game.players.map((p) => [p.id, '']));
            this.$flux.modal('score-round').show();
        },

        flipRoundSign(playerId) {
            const value = String(this.roundScores[playerId] ?? '');

            this.roundScores[playerId] = value.startsWith('-') ? value.slice(1) : `-${value}`;
        },

        saveRound() {
            const scores = Object.fromEntries(
                Object.entries(this.roundScores).map(([id, value]) => [id, parseInt(value, 10) || 0]),
            );

            this.apply((game) => model.addRound(game, scores));
            this.$flux.modal('score-round').close();
        },

        nextRound() {
            this.apply(model.nextRound);
        },

        previousRound() {
            this.apply(model.previousRound);
        },

        undo() {
            this.apply(model.undo);
        },

        openCell(playerId, round) {
            if (!this.isHost) return;

            this.cell = { playerId, round, amount: '' };
            this.$flux.modal('edit-cell').show();
        },

        get cellEntries() {
            return this.game && this.cell.playerId ? model.entriesFor(this.game, this.cell.playerId, this.cell.round) : [];
        },

        editEntry(entryId, value) {
            const points = parseInt(value, 10);

            if (Number.isNaN(points)) return;

            this.apply((game) => (points === 0 ? model.deleteEntry(game, entryId) : model.editEntry(game, entryId, points)));
        },

        deleteEntry(entryId) {
            this.apply((game) => model.deleteEntry(game, entryId));
        },

        addToCell() {
            const points = parseInt(this.cell.amount, 10) || 0;

            this.apply((game) => model.addPoints(game, this.cell.playerId, points, { round: this.cell.round }));
            this.cell.amount = '';
        },

        renamePlayer(playerId, name) {
            this.apply((game) => model.renamePlayer(game, playerId, name));
        },

        canRemovePlayer(playerId) {
            return this.game ? model.canRemovePlayer(this.game, playerId) : false;
        },

        removePlayer(playerId) {
            this.apply((game) => model.removePlayer(game, playerId));
        },

        movePlayer(playerId, offset) {
            this.apply((game) => model.movePlayer(game, playerId, offset));
        },

        addPlayer() {
            this.apply((game) => model.addPlayer(game, this.newPlayer));
            this.newPlayer = '';
        },

        setTitle(title) {
            this.apply((game) => model.setTitle(game, title));
        },

        setLowestWins(value) {
            this.apply((game) => model.setLowestWins(game, value));
        },

        /* Same players, fresh sheet, new code — the finished game stays on
         * this device (and its link) for anyone still looking at it. */
        rematch() {
            const record = { id: model.uid(), code: null, token: null, game: model.rematch(this.record.game), syncedRev: 0 };

            sync.saveRecord(record);
            sync.setCurrent(record.id);
            window.location.href = playUrl();
        },

        async copyLink() {
            try {
                await navigator.clipboard.writeText(this.shareUrl);
                this.copied = true;
                this.copyFailed = false;
                setTimeout(() => (this.copied = false), 2000);
            } catch {
                // Refused outside a secure context — the link is on screen
                // as selectable text, so point at that instead.
                this.copyFailed = true;
            }
        },
    }));
});

function ordinal(n) {
    const tens = n % 100;
    const suffix = tens >= 11 && tens <= 13 ? 'th' : { 1: 'st', 2: 'nd', 3: 'rd' }[n % 10] ?? 'th';

    return `${n}${suffix}`;
}

function signed(n) {
    return n > 0 ? `+${n}` : `${n}`;
}

export function timeAgo(timestamp, now = Date.now()) {
    if (!timestamp) return 'never';

    const seconds = Math.max(0, Math.round((now - timestamp) / 1000));

    if (seconds < 45) return 'just now';
    if (seconds < 3600) return `${Math.round(seconds / 60)} min ago`;
    if (seconds < 86400) return `${Math.round(seconds / 3600)} h ago`;

    return `${Math.round(seconds / 86400)} d ago`;
}
