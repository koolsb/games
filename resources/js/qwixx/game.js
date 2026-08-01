import * as engine from './engine';

const GAME_KEY = 'qwixx.game.v1';
const PREFS_KEY = 'qwixx.prefs.v1';

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

document.addEventListener('alpine:init', () => {
    /*
     * The whole game: engine state plus the screen wake lock. `layout` is
     * the JSON shape from Layout::toClientArray(), embedded by the page.
     */
    Alpine.data('qwixxGame', (layout, mode) => ({
        state: null,

        wlLock: null,
        wlActive: false,
        wlEnabled: true,
        wlSupported: 'wakeLock' in navigator,

        fsActive: false,
        // iPadOS supports element fullscreen (webkit-prefixed before 16.4);
        // iPhones don't, so the button hides itself there.
        fsSupported: document.fullscreenEnabled || document.webkitFullscreenEnabled || false,

        init() {
            const stored = loadJson(GAME_KEY);
            this.state = isValidGame(stored, layout.id, mode) ? stored : engine.newGame(layout.id, mode);
            this.save();

            this.wlEnabled = loadJson(PREFS_KEY)?.wakeLock ?? true;

            if (this.wlEnabled) this.wlAcquire();

            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && this.wlEnabled && !this.wlLock) this.wlAcquire();
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
            saveJson(GAME_KEY, this.state);
        },

        // -- gameplay ----------------------------------------------------

        tap(player, row, pos) {
            const rowSt = this.state.players[player].rows[row];

            if (rowSt.crosses.includes(pos)) {
                if (engine.canUncross(this.state, player, row, pos)) {
                    this.state = engine.uncross(this.state, player, row, pos);
                    this.save();
                }

                return;
            }

            if (engine.canCross(this.state, player, row, pos)) {
                this.state = engine.cross(this.state, player, row, pos);
                this.save();
            }
        },

        setPenalty(player, count) {
            this.state = engine.setPenalties(this.state, player, count);
            this.save();
        },

        togglePenalty(player, index) {
            const current = this.state.players[player].penalties;

            this.setPenalty(player, index < current ? index : index + 1);
        },

        toggleClose(player, row) {
            this.state = engine.toggleExternalClose(this.state, player, row);
            this.save();
        },

        resetGame() {
            this.state = engine.newGame(layout.id, mode);
            this.save();
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

        get gameOver() {
            return engine.isGameOver(this.state);
        },

        get gameOverReason() {
            if (engine.lockedRowCount(this.state) >= 2) return 'Two rows are locked.';

            return 'Four penalties taken.';
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
});
