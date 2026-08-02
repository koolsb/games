import { describe, expect, it } from 'vitest';
import { newPlayer } from '../../../resources/js/qwixx/engine.js';
import { STALE_AFTER_MS, isStale, mergePlayers, roomNames, seatIndex } from '../../../resources/js/qwixx/room.js';

/* A server snapshot in Room::toClientArray() shape. */
const slice = (crosses = [], penalties = 0) => ({
    rows: [0, 1, 2, 3].map((r) => ({ crosses: r === 0 ? crosses : [], locked: false, closed: false })),
    penalties,
});

const room = (players, extra = {}) => ({
    code: 'ABCD',
    layout: 'classic',
    status: 'playing',
    round: 1,
    version: 7,
    endedAt: null,
    players: players.map((player, i) => ({
        id: `p${i + 1}`,
        name: player.name ?? `Player ${i + 1}`,
        isHost: i === 0,
        state: player.state ?? null,
        lastSeenAt: player.lastSeenAt ?? Math.floor(Date.now() / 1000),
        ...player,
    })),
    ...extra,
});

describe('seatIndex', () => {
    it('finds this device among the players, in the order they joined', () => {
        const snapshot = room([{}, {}, {}]);

        expect(seatIndex(snapshot, 'p2')).toBe(1);
        expect(seatIndex(snapshot, 'nobody')).toBe(-1);
    });
});

describe('mergePlayers', () => {
    it('takes every other sheet from the server and keeps its own local', () => {
        const local = slice([0, 1, 2]);
        const snapshot = room([{ state: slice([5]) }, { state: slice([9]) }]);

        const players = mergePlayers(snapshot, 'p1', local);

        // Our own slice wins even though the server has an older copy: the
        // player holding the device is the authority on their own sheet, so
        // a slow response can never undo a tap.
        expect(players[0]).toBe(local);
        expect(players[1].rows[0].crosses).toEqual([9]);
    });

    it('blanks a seat that has not synced yet', () => {
        const players = mergePlayers(room([{ state: null }, { state: null }]), 'p1', null);

        expect(players).toEqual([newPlayer(), newPlayer()]);
    });

    it('falls back to the server copy of our own sheet when there is nothing local', () => {
        // A player rejoining on a second device, or after clearing storage.
        const players = mergePlayers(room([{ state: slice([3, 4]) }]), 'p1', null);

        expect(players[0].rows[0].crosses).toEqual([3, 4]);
    });

    it('keeps the roster order so the seat index stays meaningful', () => {
        const snapshot = room([{ state: slice([1]) }, { state: slice([2]) }, { state: slice([3]) }]);
        const players = mergePlayers(snapshot, 'p2', slice([9]));

        expect(players.map((p) => p.rows[0].crosses)).toEqual([[1], [9], [3]]);
        expect(players[seatIndex(snapshot, 'p2')].rows[0].crosses).toEqual([9]);
    });

    it('drops a local sheet on request, which is how a host restart clears the table', () => {
        // The caller passes a blank slice when the room's round has moved on.
        const players = mergePlayers(room([{ state: slice([1, 2]) }]), 'p1', newPlayer());

        expect(players[0].rows[0].crosses).toEqual([]);
    });
});

describe('roomNames', () => {
    it('lists names positionally for the standings', () => {
        expect(roomNames(room([{ name: 'Ada' }, { name: 'Bo' }]))).toEqual(['Ada', 'Bo']);
    });
});

describe('isStale', () => {
    it('flags a player who has stopped syncing', () => {
        const now = Date.now();
        const seconds = (ms) => Math.floor((now - ms) / 1000);

        expect(isStale({ lastSeenAt: seconds(2000) }, now)).toBe(false);
        expect(isStale({ lastSeenAt: seconds(STALE_AFTER_MS + 1000) }, now)).toBe(true);
    });
});
