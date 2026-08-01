/*
 * Pure Qwixx rules engine. Every function takes plain JSON-safe data and
 * returns new data — no DOM, no Alpine, no storage. The layout shape comes
 * from Layout::toClientArray():
 *
 *   { id, rows: [{ lock: 'red', cells: [{ n: 2, c: 'red' }, ...11] }, ...4] }
 *
 * Game state (persisted to localStorage by the UI layer):
 *
 *   { layout, mode: 'solo'|'duo', players: [{ rows: [{ crosses: [pos...],
 *     locked, closed }, ...4], penalties }] }
 *
 * `crosses` holds crossed positions (0-10) ascending. `locked` means this
 * player crossed position 10 themselves (earning the bonus lock mark);
 * `closed` means the row was manually marked as locked by a player outside
 * this device. Everything else is derived.
 */

export const LAST_POS = 10;
export const CROSSES_TO_LOCK = 5;
export const MAX_PENALTIES = 4;
export const PENALTY_POINTS = 5;
export const SCORE_TABLE = [0, 1, 3, 6, 10, 15, 21, 28, 36, 45, 55, 66, 78];
export const COLORS = ['red', 'yellow', 'green', 'blue'];

export function newGame(layoutId, mode) {
    const players = mode === 'duo' ? 2 : 1;

    return {
        layout: layoutId,
        mode,
        players: Array.from({ length: players }, () => ({
            rows: Array.from({ length: 4 }, () => ({ crosses: [], locked: false, closed: false })),
            penalties: 0,
        })),
    };
}

/* JSON round-trip instead of structuredClone: the state is plain JSON data,
 * and the UI hands us Alpine's reactive Proxy, which structuredClone
 * refuses to clone. */
function clone(state) {
    return JSON.parse(JSON.stringify(state));
}

function rowState(state, player, row) {
    return state.players[player].rows[row];
}

function rightmost(rowSt) {
    return rowSt.crosses.length ? rowSt.crosses[rowSt.crosses.length - 1] : -1;
}

/* A row is locked table-wide when any player on this device crossed its
 * lock, or a player marked it closed by someone off-device. */
export function isRowLockedForAll(state, row) {
    return state.players.some((p) => p.rows[row].locked || p.rows[row].closed);
}

/* Closed for this player: own lock/manual close, or (duo) the partner
 * locked it. Derived, so un-crossing a lock reopens the partner's row. */
export function isRowClosedFor(state, player, row) {
    const own = rowState(state, player, row);

    return own.locked || own.closed || state.players.some((p, i) => i !== player && p.rows[row].locked);
}

export function lockedRowCount(state) {
    let count = 0;

    for (let row = 0; row < 4; row++) {
        if (isRowLockedForAll(state, row)) count++;
    }

    return count;
}

export function isGameOver(state) {
    return lockedRowCount(state) >= 2 || state.players.some((p) => p.penalties >= MAX_PENALTIES);
}

export function canCross(state, player, row, pos) {
    const rowSt = rowState(state, player, row);

    if (pos <= rightmost(rowSt)) return false;

    if (pos === LAST_POS && rowSt.crosses.length < CROSSES_TO_LOCK) return false;

    if (isRowClosedFor(state, player, row)) {
        // Simultaneous-lock exception: when someone else locked the row this
        // roll, a player with 5+ crosses may still take the final number and
        // the lock bonus. This stays open even once the second lock ends the
        // game — that is exactly when simultaneous locks happen — and never
        // adds a new locked row, since a closed row is already locked for all.
        return pos === LAST_POS;
    }

    return !isGameOver(state);
}

export function cross(state, player, row, pos) {
    if (!canCross(state, player, row, pos)) return state;

    const next = clone(state);
    const rowSt = rowState(next, player, row);

    rowSt.crosses.push(pos);

    if (pos === LAST_POS) rowSt.locked = true;

    return next;
}

/* Only the rightmost cross can be taken back (mistake correction). */
export function canUncross(state, player, row, pos) {
    return rightmost(rowState(state, player, row)) === pos && pos >= 0;
}

export function uncross(state, player, row, pos) {
    if (!canUncross(state, player, row, pos)) return state;

    const next = clone(state);
    const rowSt = rowState(next, player, row);

    rowSt.crosses.pop();

    if (pos === LAST_POS) rowSt.locked = false;

    return next;
}

export function setPenalties(state, player, count) {
    const next = clone(state);

    next.players[player].penalties = Math.max(0, Math.min(MAX_PENALTIES, count));

    return next;
}

/* Toggle "a player off this device locked the row". No bonus mark. */
export function toggleExternalClose(state, player, row) {
    const next = clone(state);
    const rowSt = rowState(next, player, row);

    rowSt.closed = !rowSt.closed;

    return next;
}

/* Marks in each row for one player: the crossed cells plus the earned lock's
 * bonus mark. Qwixx scores by ROW — how many marks a row holds — regardless
 * of the individual cell colors, which only decide which die may fill a cell.
 * This one rule scores classic, mixed-numbers, and mixed-colors sheets alike. */
export function marksByRow(layout, playerState) {
    return layout.rows.map((_row, r) => {
        const rowSt = playerState.rows[r];

        return rowSt.crosses.length + (rowSt.locked ? 1 : 0);
    });
}

export function scoreByRow(layout, playerState) {
    return marksByRow(layout, playerState).map((marks) => SCORE_TABLE[marks]);
}

/* The same row scores, keyed by each row's lock color, for the totals strip.
 * Every layout locks its four rows in four distinct colors, so this is just a
 * 1:1 relabelling of scoreByRow. */
export function scoresByLockColor(layout, playerState) {
    const rowScores = scoreByRow(layout, playerState);

    return Object.fromEntries(layout.rows.map((row, r) => [row.lock, rowScores[r]]));
}

export function penaltyPoints(playerState) {
    return playerState.penalties * PENALTY_POINTS;
}

export function total(layout, playerState) {
    return scoreByRow(layout, playerState).reduce((sum, score) => sum + score, 0) - penaltyPoints(playerState);
}
