import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { newGame } from '../../../resources/js/scoreboard/model.js';
import * as sync from '../../../resources/js/scoreboard/sync.js';

function memoryStorage() {
    const data = new Map();

    return {
        getItem: (k) => (data.has(k) ? data.get(k) : null),
        setItem: (k, v) => data.set(k, String(v)),
        removeItem: (k) => data.delete(k),
    };
}

const json = (status, body) => ({ ok: status >= 200 && status < 300, status, json: async () => body });

const record = (overrides = {}) => ({
    id: 'local-1',
    code: null,
    token: null,
    game: newGame({ names: ['Ada', 'Bea'] }),
    syncedRev: 0,
    ...overrides,
});

beforeEach(() => {
    globalThis.localStorage = memoryStorage();
    globalThis.fetch = vi.fn();
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('push', () => {
    it('registers a game that has no code yet', async () => {
        fetch.mockResolvedValueOnce(json(201, { board: { code: 'ABCD', rev: 1 }, token: 't'.repeat(40) }));

        const patch = await sync.push(record());

        expect(patch).toEqual({ code: 'ABCD', token: 't'.repeat(40), syncedRev: 1 });
        expect(fetch).toHaveBeenCalledWith('/scoreboard/games', expect.objectContaining({ method: 'POST' }));
    });

    it('pushes to the existing code with the token', async () => {
        fetch.mockResolvedValueOnce(json(200, { board: { code: 'ABCD', rev: 5 }, accepted: true }));

        const r = record({ code: 'ABCD', token: 'tok', game: { ...record().game, rev: 5 }, syncedRev: 4 });
        const patch = await sync.push(r);

        expect(patch.syncedRev).toBe(5);

        const [url, init] = fetch.mock.calls[0];

        expect(url).toBe('/scoreboard/games/ABCD');
        expect(init.method).toBe('PUT');
        expect(init.headers['X-Scoreboard-Token']).toBe('tok');
    });

    it('reclaims the same code when the server has forgotten the game', async () => {
        fetch
            .mockResolvedValueOnce(json(404, { reason: 'not_found', message: 'gone' }))
            .mockResolvedValueOnce(json(201, { board: { code: 'ABCD', rev: 3 }, token: 'tok' }));

        const patch = await sync.push(record({ code: 'ABCD', token: 'tok', syncedRev: 0 }));

        expect(patch.code).toBe('ABCD');
        expect(JSON.parse(fetch.mock.calls[1][1].body)).toMatchObject({ code: 'ABCD', token: 'tok' });
    });

    it('starts over on a new code when the token is refused', async () => {
        fetch
            .mockResolvedValueOnce(json(403, { reason: 'not_host' }))
            .mockResolvedValueOnce(json(201, { board: { code: 'WXYZ', rev: 1 }, token: 'new' }));

        const patch = await sync.push(record({ code: 'ABCD', token: 'old' }));

        expect(patch).toMatchObject({ code: 'WXYZ', token: 'new' });
        expect(JSON.parse(fetch.mock.calls[1][1].body)).toMatchObject({ code: null, token: null });
    });

    it('reports a dropped connection as transient', async () => {
        fetch.mockRejectedValueOnce(new TypeError('Failed to fetch'));

        const error = await sync.push(record()).catch((e) => e);

        expect(error.reason).toBe('offline');
        expect(sync.isTransient(error)).toBe(true);
    });

    it('reports a server error and a rate limit as transient, a rejection as not', async () => {
        fetch
            .mockResolvedValueOnce({ ok: false, status: 502, json: async () => { throw new Error('html'); } })
            .mockResolvedValueOnce(json(429, null))
            .mockResolvedValueOnce(json(422, { message: 'bad' }));

        const errors = [];

        for (let i = 0; i < 3; i++) errors.push(await sync.push(record()).catch((e) => e));

        expect(errors.map((e) => [e.reason, sync.isTransient(e)])).toEqual([
            ['server_error', true],
            ['rate_limited', true],
            ['rejected', false],
        ]);
    });
});

describe('isDirty', () => {
    it('is dirty until the server has this revision', () => {
        const r = record({ code: 'ABCD', syncedRev: 1 });

        expect(sync.isDirty(record())).toBe(true);
        expect(sync.isDirty(r)).toBe(false);
        expect(sync.isDirty({ ...r, game: { ...r.game, rev: 2 } })).toBe(true);
    });
});

describe('local records', () => {
    it('survives a reload: save, then find by id and by code', () => {
        sync.saveRecord(record({ code: 'ABCD' }));

        expect(sync.recordById('local-1').game.players).toHaveLength(2);
        expect(sync.recordByCode('ABCD').id).toBe('local-1');
        expect(sync.recordByCode('ZZZZ')).toBeNull();
    });

    it('drops corrupted and month-old records', () => {
        const now = Date.now();

        sync.saveRecord(record({ id: 'old' }), now - 31 * 24 * 3600 * 1000);
        localStorage.setItem(
            'scoreboard.games.v1',
            JSON.stringify({ ...JSON.parse(localStorage.getItem('scoreboard.games.v1')), junk: { id: 'junk', game: {} } }),
        );
        sync.saveRecord(record({ id: 'new' }), now);

        expect(sync.hostedRecords().map((r) => r.id)).toEqual(['new']);
    });

    it('survives unreadable storage', () => {
        localStorage.setItem('scoreboard.games.v1', '{not json');

        expect(sync.loadRecords()).toEqual({});
    });

    it('forgets the current game when it is removed', () => {
        sync.saveRecord(record());
        sync.setCurrent('local-1');
        sync.removeRecord('local-1');

        expect(sync.currentId()).toBeNull();
        expect(sync.recordById('local-1')).toBeNull();
    });

    it('caches what a follower last saw', () => {
        sync.cacheBoard('ABCD', { code: 'ABCD', rev: 2 }, 1000);

        expect(sync.cachedBoard('ABCD')).toEqual({ board: { code: 'ABCD', rev: 2 }, fetchedAt: 1000 });
    });
});
