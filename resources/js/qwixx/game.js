import * as engine from './engine';
import * as room from './room';

const GAME_KEY = 'qwixx.game.v1';
const PREFS_KEY = 'qwixx.prefs.v1';
const NAMES_KEY = 'qwixx.names.v1';

/* How often a multiplayer device asks the server what everyone else has
 * done. Fast enough that a locked row lands before the next roll. */
const POLL_MS = 2000;

/* A tap should reach the table sooner than the next poll, but a run of taps
 * is one push, not five. */
const PUSH_DEBOUNCE_MS = 250;

function loadJson(key) {
    try {
        return JSON.parse(localStorage.getItem(key));
    } catch {
        return null;
    }
}

function saveJson(key, value) {
    localStorage.setItem(key, JSON.stringify(value));
}

/* Shape check for stored games, so a bad or stale payload never wedges the
 * page — anything suspicious falls back to a fresh sheet. */
function isValidGame(game, layoutId, mode) {
    return (
        game &&
        game.layout === layoutId &&
        game.mode === mode &&
        Array.isArray(game.players) &&
        game.players.length === (mode === 'duo' ? 2 : 1) &&
        game.players.every(
            (p) =>
                Number.isInteger(p.penalties) &&
                Array.isArray(p.rows) &&
                p.rows.length === 4 &&
                p.rows.every((r) => Array.isArray(r.crosses)),
        )
    );
}

function defaultNames(count) {
    return Array.from({ length: count }, (_, i) => `Player ${i + 1}`);
}

document.addEventListener('alpine:init', () => {
    /*
     * The whole game: engine state plus the screen wake lock. `layout` is
     * the JSON shape from Layout::toClientArray(), embedded by the page.
     *
     * `roomSnapshot` is set only in multiplayer, where the same component
     * also owns the lobby, the sync loop and the results. The gameplay
     * methods below are shared by all three modes — multiplayer edits the
     * local sheet through exactly the same engine calls as a solo game and
     * then tells the server about it.
     */
    Alpine.data('qwixxGame', (layout, mode, roomSnapshot = null) => ({
        state: null,
        names: [],

        // The sheet as it was before the last change on this device, so a
        // mistaken tap can be taken back — including the one that ended the
        // game. One step deep: it exists to fix a slip, not to rewind a game.
        undo: null,

        // The game-over banner lies across the middle rows. Putting it away
        // is what lets you reach the cells underneath to fix a mistake by
        // hand; it comes back on its own for the next game over.
        bannerDismissed: false,

        // -- multiplayer ---------------------------------------------------
        multi: mode === 'multi',
        room: roomSnapshot,
        code: roomSnapshot?.code ?? null,
        token: null,
        playerId: null,
        me: -1,
        round: roomSnapshot?.round ?? 1,
        syncState: 'live',
        joinName: '',
        joinError: '',
        busy: false,
        gone: false,
        copied: false,
        showStandings: true,

        pollTimer: null,
        pushTimer: null,
        inFlight: false,
        failures: 0,
        retryAt: 0,

        wlLock: null,
        wlActive: false,
        wlEnabled: true,
        wlSupported: 'wakeLock' in navigator,

        fsActive: false,
        // iPadOS supports element fullscreen (webkit-prefixed before 16.4);
        // iPhones don't, so the button hides itself there.
        fsSupported: document.fullscreenEnabled || document.webkitFullscreenEnabled || false,

        init() {
            this.multi ? this.initRoom() : this.initLocal();

            this.wlEnabled = loadJson(PREFS_KEY)?.wakeLock ?? true;

            if (this.wlEnabled) this.wlAcquire();

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState !== 'visible') return;

                if (this.wlEnabled && !this.wlLock) this.wlAcquire();

                // Catch up on everything that happened while the tab slept.
                if (this.multi) this.pump({ force: true });
            });

            // Safari refuses wake locks outside a user gesture; retry on the
            // first touch of the sheet.
            document.addEventListener(
                'pointerdown',
                () => {
                    if (this.wlEnabled && !this.wlLock) this.wlAcquire();
                },
                { once: true },
            );

            for (const event of ['fullscreenchange', 'webkitfullscreenchange']) {
                document.addEventListener(event, () => {
                    this.fsActive = !!(document.fullscreenElement || document.webkitFullscreenElement);
                });
            }
        },

        initLocal() {
            const stored = loadJson(GAME_KEY);

            this.state = isValidGame(stored, layout.id, mode) ? stored : engine.newGame(layout.id, mode);
            this.names = loadJson(NAMES_KEY) ?? defaultNames(this.state.players.length);
            this.save();
        },

        async fsToggle() {
            const doc = document;
            const el = doc.documentElement;

            try {
                if (doc.fullscreenElement || doc.webkitFullscreenElement) {
                    await (doc.exitFullscreen?.() ?? doc.webkitExitFullscreen?.());
                } else {
                    await (el.requestFullscreen?.() ?? el.webkitRequestFullscreen?.());
                }
            } catch {
                // Some browsers refuse (e.g. iPhone Safari) — leave state as is.
            }
        },

        save() {
            if (this.multi) {
                if (this.seated) saveJson(this.roomKey, { round: this.round, state: this.mine });

                return;
            }

            saveJson(GAME_KEY, this.state);
        },

        // -- gameplay ----------------------------------------------------

        /*
         * Every change goes through here so the sheet before it survives as
         * a one-step undo. The engine returns the state it was handed when
         * a move is illegal, which is how we tell a real change from a tap
         * on a closed cell — an illegal tap must not become the thing an
         * undo takes back.
         */
        mutate(label, apply) {
            const before = JSON.parse(JSON.stringify(this.state.players));
            const next = apply();

            if (next === this.state) return;

            this.state = next;
            this.undo = { label, players: before };
            this.commit();
        },

        tap(player, row, pos) {
            const rowSt = this.state.players[player].rows[row];

            if (rowSt.crosses.includes(pos)) {
                this.mutate('mark', () => engine.uncross(this.state, player, row, pos));

                return;
            }

            this.mutate('mark', () => engine.cross(this.state, player, row, pos));
        },

        setPenalty(player, count) {
            this.mutate('penalty', () => engine.setPenalties(this.state, player, count));
        },

        togglePenalty(player, index) {
            const current = this.state.players[player].penalties;

            this.setPenalty(player, index < current ? index : index + 1);
        },

        toggleClose(player, row) {
            this.mutate('lock', () => engine.toggleExternalClose(this.state, player, row));
        },

        /*
         * Take back the last change made on this device. The game-over
         * banner sits over the very cells you would otherwise tap to undo a
         * mistaken fourth penalty or a lock nobody meant to take, so the
         * banner offers this directly.
         *
         * In a room this device only owns its own sheet, so that is all it
         * restores — if someone else ended the game by accident, the undo
         * belongs on their device, and their next sync reopens the game for
         * everyone.
         */
        undoLast() {
            if (!this.undo) return;

            const { players } = this.undo;

            this.state = {
                ...this.state,
                players: this.state.players.map((player, index) =>
                    !this.multi || index === this.me ? players[index] : player,
                ),
            };

            this.undo = null;
            this.commit();
        },

        /* Persist locally, then — in multiplayer — tell the table. */
        commit() {
            // A dismissed banner belongs to one game over. Once play has
            // resumed, forget it, so the next one is announced properly.
            if (!this.gameOver) this.bannerDismissed = false;

            this.save();

            if (this.multi) this.schedulePush();
        },

        resetGame() {
            if (this.multi) {
                this.restart();

                return;
            }

            this.state = engine.newGame(layout.id, mode);
            this.undo = null;
            this.save();
        },

        setName(index, value) {
            this.names[index] = value.trim().slice(0, 14) || `Player ${index + 1}`;
            saveJson(NAMES_KEY, this.names);
        },

        // -- derived view state -------------------------------------------

        isCrossed(player, row, pos) {
            return this.state.players[player].rows[row].crosses.includes(pos);
        },

        rightmost(player, row) {
            const crosses = this.state.players[player].rows[row].crosses;

            return crosses.length ? crosses[crosses.length - 1] : -1;
        },

        cellClass(player, row, pos) {
            const crossed = this.isCrossed(player, row, pos);
            const canCross = engine.canCross(this.state, player, row, pos);

            return {
                'qx-crossed': crossed,
                'qx-skipped': !crossed && pos < this.rightmost(player, row),
                'qx-muted': !crossed && !canCross && pos > this.rightmost(player, row),
                'qx-lockable': !crossed && canCross && pos === engine.LAST_POS,
            };
        },

        rowClosed(player, row) {
            return engine.isRowClosedFor(this.state, player, row);
        },

        rowLockedSelf(player, row) {
            return this.state.players[player].rows[row].locked;
        },

        rowClosedExternally(player, row) {
            return this.rowClosed(player, row) && !this.rowLockedSelf(player, row);
        },

        rowMarkedClosed(player, row) {
            return this.state.players[player].rows[row].closed;
        },

        score(player, color) {
            return engine.scoresByLockColor(layout, this.state.players[player])[color];
        },

        penaltyCount(player) {
            return this.state.players[player].penalties;
        },

        penaltyPoints(player) {
            return engine.penaltyPoints(this.state.players[player]);
        },

        totalFor(player) {
            return engine.total(layout, this.state.players[player]);
        },

        playerName(index) {
            return this.names[index] ?? `Player ${index + 1}`;
        },

        /* Everyone's score, best first — the standings strip and the results
         * screen read the same list, so they can never disagree. */
        get standings() {
            return engine.standings(layout, this.state, this.names);
        },

        /*
         * Derived from the sheets themselves, never from the room's status.
         * A device that reloads after the last lock still lands on the
         * results, because it holds everyone's sheets — and when a player
         * takes back the mark that ended the game, the table goes straight
         * back to playing instead of being stuck behind a server flag.
         */
        get gameOver() {
            return engine.isGameOver(this.state);
        },

        get gameOverReason() {
            if (engine.lockedRowCount(this.state) >= 2) return 'Two rows are locked.';

            return 'Four penalties taken.';
        },

        // -- multiplayer ---------------------------------------------------

        get roomKey() {
            return `qwixx.room.${this.code}.v1`;
        },

        get seated() {
            return !!this.token && this.me >= 0;
        },

        get mine() {
            return this.me >= 0 ? this.state?.players[this.me] : null;
        },

        get isHost() {
            return !!this.room?.players.find((p) => p.id === this.playerId)?.isHost;
        },

        get inLobby() {
            return this.room?.status === 'lobby';
        },

        get roster() {
            return this.room?.players ?? [];
        },

        get shareUrl() {
            return `${window.location.origin}/qwixx/room/${this.code}`;
        },

        initRoom() {
            const seat = room.seatFor(this.code);

            this.joinName = room.rememberedName();

            // An unclaimed seat means this device has never joined, or the
            // room outlived its stored token — either way, ask for a name.
            if (!seat) {
                this.state = engine.newGame(layout.id, mode, this.room.players.length);
                this.applyRoom(this.room);
                this.startPolling();

                return;
            }

            this.token = seat.token;
            this.playerId = seat.playerId;
            // Before applyRoom(), which reads this.me to find the slice it
            // must not overwrite. A sheet whose last push never landed —
            // the tab died offline — is still the good copy.
            this.me = room.seatIndex(this.room, this.playerId);

            const stored = loadJson(this.roomKey);
            const mine = stored?.round === this.room.round ? stored.state : null;

            this.state = { layout: layout.id, mode, players: room.mergePlayers(this.room, this.playerId, mine) };
            this.applyRoom(this.room);
            this.startPolling();
            this.pump({ force: true });
        },

        async join() {
            if (this.busy) return;

            this.busy = true;
            this.joinError = '';

            try {
                const payload = await room.joinRoom(this.code, this.joinName);

                this.token = payload.token;
                this.playerId = payload.playerId;

                room.rememberSeat(this.code, { token: payload.token, playerId: payload.playerId });
                room.rememberName(this.joinName);

                this.applyRoom(payload.room);
                this.save();
            } catch (error) {
                this.joinError = error.message;
            } finally {
                this.busy = false;
            }
        },

        async startGame() {
            await this.act(() => room.startRoom(this.code, this.token));
        },

        async restart() {
            await this.act(() => room.restartRoom(this.code, this.token));
        },

        async act(call) {
            if (this.busy) return;

            this.busy = true;

            try {
                this.applyRoom((await call()).room);
            } catch (error) {
                this.handleError(error);
            } finally {
                this.busy = false;
            }
        },

        startPolling() {
            this.pollTimer ??= setInterval(() => {
                if (document.visibilityState === 'visible') this.pump();
            }, POLL_MS);
        },

        schedulePush() {
            clearTimeout(this.pushTimer);
            this.pushTimer = setTimeout(() => this.pump({ force: true }), PUSH_DEBOUNCE_MS);
        },

        /*
         * One round trip: hand over this device's sheet, take back everyone
         * else's. Sending the whole slice every time (rather than a diff)
         * makes a lost push self-healing — the next poll carries it.
         */
        async pump({ force = false } = {}) {
            if (this.inFlight || this.gone) return;
            if (!force && Date.now() < this.retryAt) return;

            this.inFlight = true;

            try {
                const payload = this.seated
                    ? await room.syncRoom(this.code, this.token, {
                          state: this.mine,
                          ended: engine.isGameOver(this.state),
                      })
                    : await room.fetchRoom(this.code);

                this.applyRoom(payload.room);

                this.failures = 0;
                this.retryAt = 0;
                this.syncState = 'live';
            } catch (error) {
                this.handleError(error);
            } finally {
                this.inFlight = false;
            }
        },

        /*
         * Fold a room snapshot into local state. Every seat comes from the
         * server except this device's own — the player holding the tablet is
         * the authority on their sheet, so a slow response can never undo a
         * tap they just made.
         */
        applyRoom(snapshot) {
            this.room = snapshot;
            this.names = room.roomNames(snapshot);

            const index = room.seatIndex(snapshot, this.playerId);

            // The host started another game: everyone clears together.
            const restarted = snapshot.round !== this.round;

            this.round = snapshot.round;

            // Nothing from the last game is worth taking back into this one.
            if (restarted) this.undo = null;

            const mine = restarted ? engine.newPlayer() : (index >= 0 ? this.state?.players[this.me] : null);

            this.me = index;
            this.state = {
                layout: layout.id,
                mode,
                players: room.mergePlayers(snapshot, this.playerId, mine),
            };

            // Someone else took back the mark that ended the game: the
            // banner is gone, so a dismissal of it should be too.
            if (!this.gameOver) this.bannerDismissed = false;

            if (index >= 0) this.save();
        },

        handleError(error) {
            if (error.reason === 'not_found') {
                this.gone = true;
                this.syncState = 'offline';

                return;
            }

            // Our token no longer names a seat — the room was recycled onto
            // this code. Drop it and offer to join afresh.
            if (error.reason === 'not_seated') {
                room.forgetSeat(this.code);
                this.token = null;
                this.me = -1;

                return;
            }

            // A refusal ("only the host can do that") says nothing about the
            // connection — don't let it light up the offline warning.
            if (!['offline', 'server_error', 'rate_limited'].includes(error.reason)) return;

            this.failures += 1;
            this.syncState = this.failures > 2 ? 'offline' : 'retrying';
            this.retryAt = Date.now() + Math.min(POLL_MS * 2 ** this.failures, 30000);
        },

        async copyCode() {
            try {
                await navigator.clipboard.writeText(this.shareUrl);
                this.copied = true;
                setTimeout(() => (this.copied = false), 2000);
            } catch {
                // Clipboard blocked (insecure context, or refused) — the code
                // is on screen in 3rem type anyway.
            }
        },

        playerStale(player) {
            return room.isStale(player, this.roster);
        },

        // -- wake lock -----------------------------------------------------

        async wlAcquire() {
            if (!this.wlSupported) return;

            try {
                this.wlLock = await navigator.wakeLock.request('screen');
                this.wlActive = true;
                this.wlLock.addEventListener('release', () => {
                    this.wlLock = null;
                    this.wlActive = false;
                });
            } catch {
                this.wlActive = false;
            }
        },

        async wlToggle() {
            this.wlEnabled = !this.wlEnabled;
            saveJson(PREFS_KEY, { wakeLock: this.wlEnabled });

            if (this.wlEnabled) {
                await this.wlAcquire();
            } else {
                await this.wlLock?.release();
                this.wlLock = null;
                this.wlActive = false;
            }
        },
    }));

    /* Picker page: offers to resume the stored game, if any. */
    Alpine.data('qwixxResume', () => ({
        game: null,

        init() {
            const stored = loadJson(GAME_KEY);

            if (stored?.layout && stored?.mode) this.game = stored;
        },

        get hasMarks() {
            return (
                this.game?.players.some(
                    (p) => p.penalties > 0 || p.rows.some((r) => r.crosses.length || r.closed),
                ) ?? false
            );
        },

        discard() {
            localStorage.removeItem(GAME_KEY);
            this.game = null;
        },
    }));

    /* Picker page: hosting a room, and joining one by code. Both end up at
     * the same place — /qwixx/room/{CODE}, which is also a shareable link. */
    Alpine.data('qwixxLauncher', () => ({
        name: '',
        code: '',
        hostLayout: null,
        busy: false,
        error: '',

        init() {
            this.name = room.rememberedName();
        },

        async host() {
            await this.go(() => room.createRoom(this.hostLayout, this.name));
        },

        async join() {
            const code = this.code.trim().toUpperCase();

            if (code.length !== 4) {
                this.error = 'A game code is four characters.';

                return;
            }

            // Already in this game on this device — go back to that sheet
            // rather than taking a second seat and leaving a ghost player.
            if (room.seatFor(code)) {
                window.location.href = `/qwixx/room/${code}`;

                return;
            }

            await this.go(() => room.joinRoom(code, this.name));
        },

        async go(call) {
            if (this.busy) return;

            this.busy = true;
            this.error = '';

            try {
                const payload = await call();

                room.rememberSeat(payload.room.code, { token: payload.token, playerId: payload.playerId });
                room.rememberName(this.name);

                window.location.href = `/qwixx/room/${payload.room.code}`;
            } catch (error) {
                this.error = error.message;
                this.busy = false;
            }
        },
    }));
});
