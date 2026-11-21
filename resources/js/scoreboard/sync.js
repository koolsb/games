/*
 * Where a scoreboard lives, and how it reaches everyone else.
 *
 * The scorekeeper's browser is the home of the game: every tap is written
 * to localStorage before anything touches the network, so a refresh, a
 * crashed tab or a dead signal loses nothing. The server holds a copy only
 * so other people can follow along; pushing it there is best-effort and
 * retried until it lands.
 *
 * A local record:
 *
 *   { id, code, token, game, syncedRev, savedAt }
 *
 * `code`/`token` are null until the server has issued them (a game started
 * offline has neither). The game is dirty while `game.rev > syncedRev`.
 */
import { isValidGame } from './model';

const GAMES_KEY = 'scoreboard.games.v1';
const CURRENT_KEY = 'scoreboard.current.v1';
const FOLLOW_KEY = 'scoreboard.follow.v1';
const NAMES_KEY = 'scoreboard.names.v1';

/* A finished game hangs around on the device for a month, then goes. */
const KEEP_MS = 30 * 24 * 3600 * 1000;
const FOLLOW_KEEP = 10;

export class SyncError extends Error {
    constructor(message, reason, status = 0) {
        super(message);
        this.name = 'SyncError';
        this.reason = reason;
        this.status = status;
    }
}

/* Failures worth retrying — as opposed to the server saying no. */
export function isTransient(error) {
    return ['offline', 'server_error', 'rate_limited'].includes(error?.reason);
}

async function request(path, { method = 'GET', token = null, body = null } = {}) {
    let response;

    try {
        response = await fetch(path, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(token ? { 'X-Scoreboard-Token': token } : {}),
            },
            body: body ? JSON.stringify(body) : undefined,
        });
    } catch {
        throw new SyncError('No connection.', 'offline');
    }

    let payload = null;

    try {
        payload = await response.json();
    } catch {
        // A proxy error page, or an empty body — fall through to the status.
    }

    if (!response.ok) {
        throw new SyncError(
            payload?.message ?? 'The server is not responding.',
            payload?.reason ??
                (response.status === 429 ? 'rate_limited' : response.status >= 500 ? 'server_error' : 'rejected'),
            response.status,
        );
    }

    return payload;
}

export function createBoard(game, claim = {}) {
    return request('/scoreboard/games', {
        method: 'POST',
        body: { state: game, code: claim.code ?? null, token: claim.token ?? null },
    });
}

export function updateBoard(code, token, game) {
    return request(`/scoreboard/games/${encodeURIComponent(code)}`, { method: 'PUT', token, body: { state: game } });
}

export function fetchBoard(code) {
    return request(`/scoreboard/games/${encodeURIComponent(code)}`);
}

export function isDirty(record) {
    return !record.code || record.game.rev > (record.syncedRev ?? 0);
}

/*
 * Push a record's game to the server. Returns the fields to merge back into
 * the record — not a whole record, because the scorekeeper may have tapped
 * again while this was in flight, and those taps must survive.
 *
 *  - No code yet: register the game and take the code it is given.
 *  - The server lost the game (expired, or a redeploy cleared the cache):
 *    register it again, asking for the same code back so links still work.
 *  - The token is refused (someone else now holds the code): register
 *    fresh under a new code.
 */
export async function push(record) {
    const game = record.game;

    if (!record.code) {
        return registered(await createBoard(game), game);
    }

    try {
        await updateBoard(record.code, record.token, game);

        return { code: record.code, token: record.token, syncedRev: game.rev };
    } catch (error) {
        if (error.status === 404) {
            return registered(await createBoard(game, { code: record.code, token: record.token }), game);
        }

        if (error.status === 403) {
            return registered(await createBoard(game), game);
        }

        throw error;
    }
}

function registered(payload, game) {
    return { code: payload.board.code, token: payload.token, syncedRev: game.rev };
}

// -- local storage -------------------------------------------------------

function readJson(key, fallback) {
    try {
        return JSON.parse(localStorage.getItem(key)) ?? fallback;
    } catch {
        return fallback;
    }
}

function writeJson(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));

        return true;
    } catch {
        // Private mode or a full quota. The game still works for this page
        // view; there is nothing better to do than carry on.
        return false;
    }
}

function validRecord(record) {
    return !!record && typeof record.id === 'string' && isValidGame(record.game);
}

export function loadRecords(now = Date.now()) {
    const all = readJson(GAMES_KEY, {});

    return Object.fromEntries(
        Object.entries(typeof all === 'object' ? all : {}).filter(
            ([, record]) => validRecord(record) && now - (record.savedAt ?? 0) < KEEP_MS,
        ),
    );
}

export function saveRecord(record, now = Date.now()) {
    const all = loadRecords(now);

    all[record.id] = { ...record, savedAt: now };

    return writeJson(GAMES_KEY, all);
}

export function removeRecord(id) {
    const all = loadRecords();

    delete all[id];
    writeJson(GAMES_KEY, all);

    if (currentId() === id) {
        try {
            localStorage.removeItem(CURRENT_KEY);
        } catch {
            // Nothing to do.
        }
    }
}

export function recordByCode(code) {
    return Object.values(loadRecords()).find((record) => record.code === code) ?? null;
}

export function recordById(id) {
    return loadRecords()[id] ?? null;
}

/* Most recently touched first — the order the resume list shows. */
export function hostedRecords() {
    return Object.values(loadRecords()).sort((a, b) => (b.savedAt ?? 0) - (a.savedAt ?? 0));
}

export function currentId() {
    try {
        return localStorage.getItem(CURRENT_KEY);
    } catch {
        return null;
    }
}

export function setCurrent(id) {
    try {
        localStorage.setItem(CURRENT_KEY, id);
    } catch {
        // Nothing to do.
    }
}

/* The last snapshot a follower saw, so a refresh with no signal still
 * shows the scores rather than a spinner. */
export function cachedBoard(code) {
    return readJson(FOLLOW_KEY, {})?.[code] ?? null;
}

export function cacheBoard(code, board, now = Date.now()) {
    const all = readJson(FOLLOW_KEY, {}) ?? {};

    all[code] = { board, fetchedAt: now };

    const kept = Object.entries(all)
        .sort(([, a], [, b]) => (b.fetchedAt ?? 0) - (a.fetchedAt ?? 0))
        .slice(0, FOLLOW_KEEP);

    writeJson(FOLLOW_KEY, Object.fromEntries(kept));
}

export function rememberedNames() {
    const names = readJson(NAMES_KEY, []);

    return Array.isArray(names) ? names.filter((n) => typeof n === 'string') : [];
}

export function rememberNames(names) {
    writeJson(NAMES_KEY, names);
}
