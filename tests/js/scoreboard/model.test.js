import { describe, expect, it } from 'vitest';
import * as m from '../../../resources/js/scoreboard/model.js';

const game = (names = ['Ada', 'Bea', 'Cy'], opts = {}) => m.newGame({ names, ...opts });
const ids = (g) => g.players.map((p) => p.id);

describe('newGame', () => {
    it('names blank seats and trims the title', () => {
        const g = m.newGame({ title: '  Rummy  ', names: ['Ada', '  ', ''] });

        expect(g.title).toBe('Rummy');
        expect(g.players.map((p) => p.name)).toEqual(['Ada', 'Player 2', 'Player 3']);
        expect(g.round).toBe(1);
        expect(g.rev).toBe(1);
        expect(m.isValidGame(g)).toBe(true);
    });

    it('caps the table at the player limit', () => {
        expect(m.newGame({ names: Array(20).fill('x') }).players).toHaveLength(m.MAX_PLAYERS);
    });
});

describe('scoring', () => {
    it('adds points and bumps the revision every time', () => {
        let g = game();
        const [a, b] = ids(g);

        g = m.addPoints(g, a, 5);
        g = m.addPoints(g, a, -2);
        g = m.addPoints(g, b, '7');

        expect(m.totals(g)).toEqual({ [a]: 3, [b]: 7, [ids(g)[2]]: 0 });
        expect(g.rev).toBe(4);
    });

    it('ignores zero, junk and unknown players', () => {
        const g = game();

        expect(m.addPoints(g, ids(g)[0], 0)).toBe(g);
        expect(m.addPoints(g, ids(g)[0], 'abc')).toBe(g);
        expect(m.addPoints(g, 'ghost', 5)).toBe(g);
    });

    it('scores a whole round and moves on', () => {
        let g = game();
        const [a, b, c] = ids(g);

        g = m.addRound(g, { [a]: 10, [b]: '', [c]: -5 });

        expect(g.round).toBe(2);
        expect(g.entries).toHaveLength(3);
        expect(new Set(g.entries.map((e) => e.batch)).size).toBe(1);
        expect(m.totals(g)).toEqual({ [a]: 10, [b]: 0, [c]: -5 });
    });

    it('can add to an earlier round when fixing the history', () => {
        let g = m.nextRound(m.nextRound(game()));
        g = m.addPoints(g, ids(g)[0], 4, { round: 1 });

        expect(g.entries[0].round).toBe(1);
        expect(g.round).toBe(3);
    });
});

describe('undo', () => {
    it('takes back a quick add without touching the round', () => {
        let g = m.nextRound(game());
        g = m.addPoints(g, ids(g)[0], 5);
        g = m.undo(g);

        expect(g.entries).toEqual([]);
        expect(g.round).toBe(2);
    });

    it('takes back a whole round and returns to it', () => {
        let g = game();
        const [a] = ids(g);

        g = m.addPoints(g, a, 1);
        g = m.addRound(g, { [a]: 10 });
        expect(m.lastBatch(g)).toMatchObject({ kind: 'round', round: 1 });

        g = m.undo(g);

        expect(g.round).toBe(1);
        expect(g.entries).toHaveLength(1);
        expect(m.totals(g)[a]).toBe(1);
    });

    it('does nothing with nothing to undo', () => {
        const g = game();

        expect(m.undo(g)).toBe(g);
        expect(m.lastBatch(g)).toBeNull();
    });
});

describe('rounds', () => {
    it('only steps back over an empty round', () => {
        let g = m.nextRound(game());

        expect(m.canStepBack(g)).toBe(true);

        g = m.addPoints(g, ids(g)[0], 3);

        expect(m.canStepBack(g)).toBe(false);
        expect(m.previousRound(g)).toBe(g);
        expect(m.canStepBack(game())).toBe(false);
    });
});

describe('standings', () => {
    it('ranks highest first, sharing ranks on ties, in seat order', () => {
        let g = game();
        const [a, b, c] = ids(g);

        g = m.addRound(g, { [a]: 5, [b]: 9, [c]: 5 });

        expect(m.standings(g).map((p) => [p.name, p.total, p.rank, p.leading])).toEqual([
            ['Ada', 5, 2, false],
            ['Bea', 9, 1, true],
            ['Cy', 5, 2, false],
        ]);
    });

    it('ranks lowest first when lowest wins', () => {
        let g = game(['Ada', 'Bea'], { lowestWins: true });
        const [a, b] = ids(g);

        g = m.addRound(g, { [a]: 5, [b]: 9 });

        expect(m.standings(g).find((p) => p.leading).name).toBe('Ada');
    });

    it('names no leader before anything is scored', () => {
        expect(m.standings(game()).some((p) => p.leading)).toBe(false);
    });
});

describe('roundGrid', () => {
    it('sums each player per round, blank where nothing was scored', () => {
        let g = game(['Ada', 'Bea']);
        const [a, b] = ids(g);

        g = m.addPoints(g, a, 2);
        g = m.addPoints(g, a, 3);
        g = m.nextRound(m.nextRound(g));
        g = m.addPoints(g, b, 4);

        expect(m.roundGrid(g)).toEqual([
            { round: 1, cells: { [a]: 5, [b]: null } },
            { round: 2, cells: { [a]: null, [b]: null } },
            { round: 3, cells: { [a]: null, [b]: 4 } },
        ]);
    });

    it('edits and deletes single entries', () => {
        let g = game(['Ada']);
        const [a] = ids(g);

        g = m.addPoints(g, a, 2);
        g = m.addPoints(g, a, 3);

        const [first, second] = m.entriesFor(g, a, 1);

        g = m.deleteEntry(m.editEntry(g, first.id, 10), second.id);

        expect(m.totals(g)[a]).toBe(10);
    });
});

describe('players', () => {
    it('adds, renames and reorders', () => {
        let g = game(['Ada', 'Bea']);

        g = m.addPlayer(g, 'Cy');
        g = m.renamePlayer(g, ids(g)[0], '  ');
        g = m.movePlayer(g, ids(g)[2], -2);

        expect(g.players.map((p) => p.name)).toEqual(['Cy', 'Player 1', 'Bea']);
    });

    it('only removes a player with nothing scored', () => {
        let g = game(['Ada', 'Bea']);
        const [a, b] = ids(g);

        g = m.addPoints(g, a, 1);

        expect(m.removePlayer(g, a)).toBe(g);
        expect(ids(m.removePlayer(g, b))).toEqual([a]);
    });

    it('starts a rematch with the same table and nothing scored', () => {
        let g = m.addRound(game(), {});
        const r = m.rematch(g);

        expect(r.players).toEqual(g.players);
        expect(r.entries).toEqual([]);
        expect(r.round).toBe(1);
    });
});

describe('isValidGame', () => {
    it('rejects junk from storage or the network', () => {
        expect(m.isValidGame(null)).toBe(false);
        expect(m.isValidGame({})).toBe(false);
        expect(m.isValidGame({ ...game(), entries: [{ playerId: 'x', points: 'lots', round: 1 }] })).toBe(false);
    });
});
