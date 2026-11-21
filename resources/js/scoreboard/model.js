/*
 * The scoreboard's game model. Pure functions over a plain object, so the
 * same code runs in the scorekeeper's browser, in every follower's browser
 * (to derive totals from the snapshot the server hands back) and in tests.
 *
 *   { title, lowestWins, round, rev,
 *     players: [{ id, name }],
 *     entries: [{ id, batch, kind: 'add'|'round', playerId, points, round, at }] }
 *
 * Only the entry log is stored. Totals, standings and the round-by-round
 * history are recomputed from it every time — undo is "drop the last
 * batch", never bespoke inverse arithmetic that could drift.
 *
 * Every mutation returns a new game with `rev` one higher. The server keeps
 * only the highest revision it has seen, which is what makes a push safe to
 * retry on a flaky connection.
 */

export const MAX_PLAYERS = 12;
export const NAME_MAX = 20;
export const TITLE_MAX = 40;

export function uid() {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();

    // crypto.randomUUID is secure-context only; a host on a LAN address
    // over plain http still needs unique-enough ids.
    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
}

export function cleanName(name, seat) {
    const trimmed = String(name ?? '')
        .replace(/\p{C}+/gu, '')
        .trim()
        .slice(0, NAME_MAX);

    return trimmed === '' ? `Player ${seat}` : trimmed;
}

export function newGame({ title = '', lowestWins = false, names = [] } = {}) {
    return {
        title: String(title).trim().slice(0, TITLE_MAX),
        lowestWins: Boolean(lowestWins),
        round: 1,
        rev: 1,
        players: names.slice(0, MAX_PLAYERS).map((name, i) => ({ id: uid(), name: cleanName(name, i + 1) })),
        entries: [],
    };
}

/* Same table, same rules, nothing scored. */
export function rematch(game) {
    return { ...game, round: 1, rev: 1, entries: [] };
}

function bump(game, changes) {
    return { ...game, ...changes, rev: (game.rev ?? 0) + 1 };
}

function toPoints(value) {
    const n = Math.trunc(Number(value));

    return Number.isFinite(n) ? n : 0;
}

// -- scoring -------------------------------------------------------------

/*
 * Points for one player, whenever they happen — in the current round, or
 * an earlier one when correcting the history. Zero is a no-op.
 */
export function addPoints(game, playerId, points, { round = game.round, now = Date.now() } = {}) {
    const value = toPoints(points);

    if (value === 0 || !game.players.some((p) => p.id === playerId)) return game;

    const id = uid();

    return bump(game, {
        entries: [
            ...game.entries,
            { id, batch: id, kind: 'add', playerId, points: value, round: Math.max(1, Math.trunc(round)), at: now },
        ],
    });
}

/*
 * A whole round at once: one entry per player (a blank is 0, and still
 * recorded, so the history shows the round was played), then on to the
 * next round.
 */
export function addRound(game, scores, now = Date.now()) {
    const batch = uid();

    const entries = game.players.map((player) => ({
        id: uid(),
        batch,
        kind: 'round',
        playerId: player.id,
        points: toPoints(scores?.[player.id]),
        round: game.round,
        at: now,
    }));

    return bump(game, { entries: [...game.entries, ...entries], round: game.round + 1 });
}

/* Move the round counter. Going back is only allowed onto a round that
 * exists, and never below 1. */
export function setRound(game, round) {
    const next = Math.max(1, Math.trunc(round));

    return next === game.round ? game : bump(game, { round: next });
}

export function nextRound(game) {
    return setRound(game, game.round + 1);
}

/* Can the round counter step back without hiding scores? Only if the
 * current round has nothing in it yet. */
export function canStepBack(game) {
    return game.round > 1 && !game.entries.some((e) => e.round === game.round);
}

export function previousRound(game) {
    return canStepBack(game) ? setRound(game, game.round - 1) : game;
}

/* What undo would take back, for the button's label. */
export function lastBatch(game) {
    const last = game.entries.at(-1);

    if (!last) return null;

    const entries = game.entries.filter((e) => e.batch === last.batch);

    return { kind: last.kind, round: last.round, entries };
}

/*
 * Take back the last thing entered. A saved round also hands its round
 * number back, so "Score round 3" is ready to fill in again.
 */
export function undo(game) {
    const batch = lastBatch(game);

    if (!batch) return game;

    const ids = new Set(batch.entries.map((e) => e.id));

    return bump(game, {
        entries: game.entries.filter((e) => !ids.has(e.id)),
        round: batch.kind === 'round' ? batch.round : game.round,
    });
}

export function editEntry(game, entryId, points) {
    const value = toPoints(points);

    return bump(game, {
        entries: game.entries.map((e) => (e.id === entryId ? { ...e, points: value } : e)),
    });
}

export function deleteEntry(game, entryId) {
    return bump(game, { entries: game.entries.filter((e) => e.id !== entryId) });
}

export function setTitle(game, title) {
    const next = String(title ?? '').trim().slice(0, TITLE_MAX);

    return next === game.title ? game : bump(game, { title: next });
}

export function setLowestWins(game, lowestWins) {
    return Boolean(lowestWins) === game.lowestWins ? game : bump(game, { lowestWins: Boolean(lowestWins) });
}

// -- players -------------------------------------------------------------

export function addPlayer(game, name) {
    if (game.players.length >= MAX_PLAYERS) return game;

    return bump(game, {
        players: [...game.players, { id: uid(), name: cleanName(name, game.players.length + 1) }],
    });
}

export function renamePlayer(game, playerId, name) {
    const seat = game.players.findIndex((p) => p.id === playerId) + 1;

    if (seat === 0) return game;

    return bump(game, {
        players: game.players.map((p) => (p.id === playerId ? { ...p, name: cleanName(name, seat) } : p)),
    });
}

/* Only a player with nothing scored can leave — otherwise their points
 * would silently vanish from the history. */
export function canRemovePlayer(game, playerId) {
    return game.players.length > 1 && !game.entries.some((e) => e.playerId === playerId);
}

export function removePlayer(game, playerId) {
    if (!canRemovePlayer(game, playerId)) return game;

    return bump(game, { players: game.players.filter((p) => p.id !== playerId) });
}

export function movePlayer(game, playerId, offset) {
    const from = game.players.findIndex((p) => p.id === playerId);
    const to = from + offset;

    if (from < 0 || to < 0 || to >= game.players.length) return game;

    const players = [...game.players];
    const [player] = players.splice(from, 1);

    players.splice(to, 0, player);

    return bump(game, { players });
}

// -- derived -------------------------------------------------------------

export function totals(game) {
    const sums = Object.fromEntries(game.players.map((p) => [p.id, 0]));

    for (const entry of game.entries) {
        if (entry.playerId in sums) sums[entry.playerId] += entry.points;
    }

    return sums;
}

/*
 * Players in seat order, each with a total and a rank. Tied players share
 * a rank (1, 1, 3). Nobody "leads" before anything has been scored — a
 * trophy on everyone at 0 would just be noise.
 */
export function standings(game) {
    const sums = totals(game);
    const sorted = [...game.players].sort((a, b) =>
        game.lowestWins ? sums[a.id] - sums[b.id] : sums[b.id] - sums[a.id],
    );
    const ranks = {};

    sorted.forEach((player, i) => {
        const prev = sorted[i - 1];

        ranks[player.id] = prev && sums[prev.id] === sums[player.id] ? ranks[prev.id] : i + 1;
    });

    const scored = game.entries.length > 0;

    return game.players.map((player) => ({
        ...player,
        total: sums[player.id],
        rank: ranks[player.id],
        leading: scored && ranks[player.id] === 1,
    }));
}

/*
 * The history table: one row per round that has anything in it, up to the
 * latest. A cell is the sum of that player's entries in that round, or
 * null if they had none — blank reads differently from a scored 0.
 */
export function roundGrid(game) {
    const last = game.entries.reduce((max, e) => Math.max(max, e.round), 0);
    const rows = [];

    for (let round = 1; round <= last; round++) {
        const cells = Object.fromEntries(game.players.map((p) => [p.id, null]));

        for (const entry of game.entries) {
            if (entry.round === round && entry.playerId in cells) {
                cells[entry.playerId] = (cells[entry.playerId] ?? 0) + entry.points;
            }
        }

        rows.push({ round, cells });
    }

    return rows;
}

export function entriesFor(game, playerId, round) {
    return game.entries.filter((e) => e.playerId === playerId && e.round === round);
}

/* Shape check for anything read back from storage or the network, so a
 * stale or corrupted payload can never wedge the page. */
export function isValidGame(game) {
    return (
        !!game &&
        typeof game === 'object' &&
        Number.isInteger(game.round) &&
        Number.isInteger(game.rev) &&
        Array.isArray(game.players) &&
        game.players.every((p) => p && typeof p.id === 'string' && typeof p.name === 'string') &&
        Array.isArray(game.entries) &&
        game.entries.every(
            (e) => e && typeof e.playerId === 'string' && Number.isInteger(e.points) && Number.isInteger(e.round),
        )
    );
}
