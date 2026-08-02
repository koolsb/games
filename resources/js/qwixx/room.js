/*
 * Multiplayer transport for Qwixx. Nothing here knows the rules — it moves
 * one player's sheet to the server and everyone else's back, so the engine
 * in every browser can run the same game over the same data.
 *
 * The server's room shape (Room::toClientArray()):
 *
 *   { code, layout, status: 'lobby'|'playing'|'ended', round, version,
 *     endedAt, players: [{ id, name, isHost, state, lastSeenAt }] }
 *
 * where `state` is the engine's per-player slice, or null for a seat that
 * has not synced yet.
 */
import { newPlayer } from './engine';

const SEATS_KEY = 'qwixx.seats.v1';
const NAME_KEY = 'qwixx.name.v1';

/* How long a player can go without syncing before the standings strip calls
 * them out. Four missed polls — long enough to survive a lock screen. */
export const STALE_AFTER_MS = 15000;

export class RoomError extends Error {
    constructor(message, reason, status = 0) {
        super(message);
        this.name = 'RoomError';
        this.reason = reason;
        this.status = status;
    }
}

async function request(path, { method = 'GET', token = null, body = null } = {}) {
    let response;

    try {
        response = await fetch(path, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(token ? { 'X-Qwixx-Token': token } : {}),
            },
            body: body ? JSON.stringify(body) : undefined,
        });
    } catch {
        throw new RoomError('No connection to the game.', 'offline');
    }

    let payload = null;

    try {
        payload = await response.json();
    } catch {
        // A proxy error page, or an empty body — fall through to the status.
    }

    if (!response.ok) {
        throw new RoomError(
            payload?.message ?? 'The game server is not responding.',
            payload?.reason ?? (response.status === 429 ? 'rate_limited' : 'server_error'),
            response.status,
        );
    }

    return payload;
}

export function createRoom(layout, name) {
    return request('/qwixx/rooms', { method: 'POST', body: { layout, name } });
}

export function joinRoom(code, name) {
    return request(`/qwixx/rooms/${encodeURIComponent(code)}/join`, { method: 'POST', body: { name } });
}

export function fetchRoom(code) {
    return request(`/qwixx/rooms/${encodeURIComponent(code)}`);
}

export function syncRoom(code, token, { state = null, ended = false } = {}) {
    return request(`/qwixx/rooms/${encodeURIComponent(code)}/sync`, {
        method: 'POST',
        token,
        body: { state, ended },
    });
}

export function startRoom(code, token) {
    return request(`/qwixx/rooms/${encodeURIComponent(code)}/start`, { method: 'POST', token });
}

export function restartRoom(code, token) {
    return request(`/qwixx/rooms/${encodeURIComponent(code)}/restart`, { method: 'POST', token });
}

// -- merging -------------------------------------------------------------

export function seatIndex(room, playerId) {
    return room.players.findIndex((player) => player.id === playerId);
}

/*
 * Build the engine's `players` array from a room snapshot. Every seat comes
 * from the server except this device's own, which stays local: the player
 * tapping the sheet is the authority on it, so their marks never wait for a
 * round trip and a slow response can never undo them.
 */
export function mergePlayers(room, playerId, localState) {
    return room.players.map((player) =>
        player.id === playerId ? (localState ?? player.state ?? newPlayer()) : (player.state ?? newPlayer()),
    );
}

export function roomNames(room) {
    return room.players.map((player) => player.name);
}

export function isStale(player, now = Date.now()) {
    return now - player.lastSeenAt * 1000 > STALE_AFTER_MS;
}

// -- this device's seats -------------------------------------------------

function readJson(key, fallback = null) {
    try {
        return JSON.parse(localStorage.getItem(key)) ?? fallback;
    } catch {
        return fallback;
    }
}

/*
 * Seats are kept per code so a device that hosted one game and joined
 * another still returns to the right seat in each — a refresh, a crashed
 * tab or a locked iPad rejoins rather than taking a new sheet.
 */
export function seatFor(code) {
    return readJson(SEATS_KEY, {})?.[code] ?? null;
}

export function rememberSeat(code, seat) {
    const seats = readJson(SEATS_KEY, {}) ?? {};

    seats[code] = seat;

    localStorage.setItem(SEATS_KEY, JSON.stringify(seats));
}

export function forgetSeat(code) {
    const seats = readJson(SEATS_KEY, {}) ?? {};

    delete seats[code];

    localStorage.setItem(SEATS_KEY, JSON.stringify(seats));
}

export function rememberedName() {
    return localStorage.getItem(NAME_KEY) ?? '';
}

export function rememberName(name) {
    if (name) localStorage.setItem(NAME_KEY, name);
}
