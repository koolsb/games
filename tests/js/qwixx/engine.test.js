import { describe, expect, it } from 'vitest';
import {
    SCORE_TABLE,
    canCross,
    canUncross,
    cross,
    isGameOver,
    isRowClosedFor,
    lockedRowCount,
    marksByRow,
    newGame,
    newPlayer,
    penaltyPoints,
    scoreByRow,
    scoresByLockColor,
    setPenalties,
    standings,
    toggleExternalClose,
    total,
    uncross,
} from '../../../resources/js/qwixx/engine.js';

/* Classic layout in Layout::toClientArray() shape. */
const solid = (lock, numbers) => ({ lock, cells: numbers.map((n) => ({ n, c: lock })) });
const asc = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
const desc = [...asc].reverse();
const classic = {
    id: 'classic',
    rows: [solid('red', asc), solid('yellow', asc), solid('green', desc), solid('blue', desc)],
};

/* Mixed-colors row 0 from the physical sheet: 3 yellow, 3 blue, 3 green, 2 red, red lock. */
const mixedColors = {
    id: 'mixed-colors',
    rows: [
        {
            lock: 'red',
            cells: [
                { n: 2, c: 'yellow' }, { n: 3, c: 'yellow' }, { n: 4, c: 'yellow' },
                { n: 5, c: 'blue' }, { n: 6, c: 'blue' }, { n: 7, c: 'blue' },
                { n: 8, c: 'green' }, { n: 9, c: 'green' }, { n: 10, c: 'green' },
                { n: 11, c: 'red' }, { n: 12, c: 'red' },
            ],
        },
        solid('yellow', asc),
        solid('green', desc),
        solid('blue', desc),
    ],
};

const crossAll = (state, player, row, positions) =>
    positions.reduce((s, pos) => cross(s, player, row, pos), state);

describe('newGame', () => {
    it('creates one player for solo and two for duo', () => {
        expect(newGame('classic', 'solo').players).toHaveLength(1);
        expect(newGame('classic', 'duo').players).toHaveLength(2);
    });

    it('takes an explicit roster size for multiplayer', () => {
        expect(newGame('classic', 'multi', 5).players).toHaveLength(5);
        // A room always has at least the host in it.
        expect(newGame('classic', 'multi', 0).players).toHaveLength(1);
    });

    it('hands out independent blank slices', () => {
        const state = newGame('classic', 'multi', 3);

        state.players[0].rows[0].crosses.push(4);

        expect(state.players[1].rows[0].crosses).toEqual([]);
        expect(newPlayer()).toEqual({
            rows: [0, 1, 2, 3].map(() => ({ crosses: [], locked: false, closed: false })),
            penalties: 0,
        });
    });
});

describe('left-to-right crossing', () => {
    it('allows any open position to the right of the last cross', () => {
        let state = cross(newGame('classic', 'solo'), 0, 0, 3);

        expect(state.players[0].rows[0].crosses).toEqual([3]);
        expect(canCross(state, 0, 0, 4)).toBe(true);
        expect(canCross(state, 0, 0, 9)).toBe(true);
    });

    it('permanently blocks skipped positions and the crossed one', () => {
        const state = cross(newGame('classic', 'solo'), 0, 0, 5);

        for (const pos of [0, 1, 2, 3, 4, 5]) {
            expect(canCross(state, 0, 0, pos)).toBe(false);
        }
    });

    it('does not mutate state on an invalid cross', () => {
        const state = cross(newGame('classic', 'solo'), 0, 0, 5);
        const after = cross(state, 0, 0, 2);

        expect(after).toBe(state);
    });
});

describe('locking a row', () => {
    it('refuses the last position with fewer than five crosses', () => {
        const state = crossAll(newGame('classic', 'solo'), 0, 0, [0, 1, 2, 3]);

        expect(canCross(state, 0, 0, 10)).toBe(false);
    });

    it('locks the row and grants a 12th-mark bonus with five or more crosses', () => {
        const state = crossAll(newGame('classic', 'solo'), 0, 0, [0, 1, 2, 3, 4, 10]);
        const row = state.players[0].rows[0];

        expect(row.locked).toBe(true);
        expect(marksByRow(classic, state.players[0])[0]).toBe(7); // 6 crosses + lock
        expect(lockedRowCount(state)).toBe(1);
    });

    it('closes a self-locked row to further crosses', () => {
        const state = crossAll(newGame('classic', 'solo'), 0, 0, [0, 1, 2, 3, 4, 10]);

        expect(isRowClosedFor(state, 0, 0)).toBe(true);
        expect(canCross(state, 0, 0, 9)).toBe(false);
    });
});

describe('uncross (mistake correction)', () => {
    it('only removes the rightmost cross', () => {
        const state = crossAll(newGame('classic', 'solo'), 0, 0, [2, 5, 7]);

        expect(canUncross(state, 0, 0, 5)).toBe(false);
        expect(canUncross(state, 0, 0, 7)).toBe(true);
        expect(uncross(state, 0, 0, 7).players[0].rows[0].crosses).toEqual([2, 5]);
    });

    it('clears the lock when the last position is uncrossed', () => {
        let state = crossAll(newGame('classic', 'solo'), 0, 0, [0, 1, 2, 3, 4, 10]);
        state = uncross(state, 0, 0, 10);

        expect(state.players[0].rows[0].locked).toBe(false);
        expect(lockedRowCount(state)).toBe(0);
    });
});

describe('external close (physical player locked the row)', () => {
    it('closes the row without a bonus mark and keeps existing marks', () => {
        let state = crossAll(newGame('classic', 'solo'), 0, 0, [0, 1, 2]);
        state = toggleExternalClose(state, 0, 0);

        expect(isRowClosedFor(state, 0, 0)).toBe(true);
        expect(canCross(state, 0, 0, 5)).toBe(false);
        expect(marksByRow(classic, state.players[0])[0]).toBe(3);
        expect(lockedRowCount(state)).toBe(1);
    });

    it('reopens when toggled back', () => {
        let state = toggleExternalClose(newGame('classic', 'solo'), 0, 0);
        state = toggleExternalClose(state, 0, 0);

        expect(isRowClosedFor(state, 0, 0)).toBe(false);
    });
});

describe('simultaneous-lock exception', () => {
    it('lets a player with five crosses still take the final cell of an externally closed row', () => {
        let state = crossAll(newGame('classic', 'solo'), 0, 0, [0, 1, 2, 3, 4]);
        state = toggleExternalClose(state, 0, 0);

        expect(canCross(state, 0, 0, 9)).toBe(false);
        expect(canCross(state, 0, 0, 10)).toBe(true);

        state = cross(state, 0, 0, 10);
        expect(state.players[0].rows[0].locked).toBe(true);
        expect(marksByRow(classic, state.players[0])[0]).toBe(7);
    });

    it('still requires five crosses in the closed row', () => {
        let state = crossAll(newGame('classic', 'solo'), 0, 0, [0, 1, 2, 3]);
        state = toggleExternalClose(state, 0, 0);

        expect(canCross(state, 0, 0, 10)).toBe(false);
    });

    it('works in duo when the partner locked the row', () => {
        let state = newGame('classic', 'duo');
        state = crossAll(state, 1, 0, [1, 2, 3, 4, 5]); // player 1 builds up five crosses
        state = crossAll(state, 0, 0, [0, 1, 2, 3, 4, 10]); // player 0 locks red

        expect(isRowClosedFor(state, 1, 0)).toBe(true);
        expect(canCross(state, 1, 0, 7)).toBe(false);
        expect(canCross(state, 1, 0, 10)).toBe(true);

        state = cross(state, 1, 0, 10);
        expect(marksByRow(classic, state.players[1])[0]).toBe(7);
        expect(lockedRowCount(state)).toBe(1); // same row, counted once
    });

    it('remains available right after the second lock ends the game', () => {
        let state = newGame('classic', 'duo');
        state = crossAll(state, 0, 0, [0, 1, 2, 3, 4, 10]); // lock 1
        state = crossAll(state, 1, 1, [0, 1, 2, 3, 4]);
        state = crossAll(state, 0, 1, [0, 1, 2, 3, 4, 10]); // lock 2 -> game over

        expect(isGameOver(state)).toBe(true);
        expect(canCross(state, 1, 1, 10)).toBe(true); // partner's simultaneous lock

        state = cross(state, 1, 1, 10);
        expect(state.players[1].rows[1].locked).toBe(true);
        expect(lockedRowCount(state)).toBe(2);
    });
});

describe('duo shared locks', () => {
    it('closes the row for the partner without giving them a mark', () => {
        let state = newGame('classic', 'duo');
        state = crossAll(state, 0, 2, [0, 1, 2, 3, 4, 10]);

        expect(isRowClosedFor(state, 1, 2)).toBe(true);
        expect(canCross(state, 1, 2, 3)).toBe(false);
        expect(marksByRow(classic, state.players[1])[2]).toBe(0);
    });

    it('reopens the partner row when the lock is uncrossed', () => {
        let state = newGame('classic', 'duo');
        state = crossAll(state, 0, 2, [0, 1, 2, 3, 4, 10]);
        state = uncross(state, 0, 2, 10);

        expect(isRowClosedFor(state, 1, 2)).toBe(false);
        expect(canCross(state, 1, 2, 3)).toBe(true);
    });
});

describe('penalties', () => {
    it('clamps between 0 and 4 and costs five points each', () => {
        let state = setPenalties(newGame('classic', 'solo'), 0, 3);

        expect(penaltyPoints(state.players[0])).toBe(15);
        expect(setPenalties(state, 0, 9).players[0].penalties).toBe(4);
        expect(setPenalties(state, 0, -2).players[0].penalties).toBe(0);
    });
});

describe('game over', () => {
    it('ends at the fourth penalty of any player', () => {
        let state = setPenalties(newGame('classic', 'duo'), 1, 4);

        expect(isGameOver(state)).toBe(true);
        expect(canCross(state, 0, 0, 0)).toBe(false);
    });

    it('ends at two locked rows in any combination of sources', () => {
        // own + own
        let state = crossAll(newGame('classic', 'solo'), 0, 0, [0, 1, 2, 3, 4, 10]);
        state = crossAll(state, 0, 1, [0, 1, 2, 3, 4, 10]);
        expect(isGameOver(state)).toBe(true);

        // own + external
        state = crossAll(newGame('classic', 'solo'), 0, 0, [0, 1, 2, 3, 4, 10]);
        state = toggleExternalClose(state, 0, 1);
        expect(isGameOver(state)).toBe(true);

        // external + external
        state = toggleExternalClose(newGame('classic', 'solo'), 0, 0);
        state = toggleExternalClose(state, 0, 3);
        expect(isGameOver(state)).toBe(true);

        // duo cross-player: one lock each
        state = newGame('classic', 'duo');
        state = crossAll(state, 0, 0, [0, 1, 2, 3, 4, 10]);
        state = crossAll(state, 1, 1, [0, 1, 2, 3, 4, 10]);
        expect(isGameOver(state)).toBe(true);
    });

    it('blocks ordinary crosses once over', () => {
        let state = setPenalties(newGame('classic', 'solo'), 0, 4);
        const after = cross(state, 0, 2, 0);

        expect(after).toBe(state);
    });
});

describe('reactive-proxy state (Alpine hands the engine a Proxy)', () => {
    // Deep proxy, like Alpine's reactivity wrapper. structuredClone throws
    // "Proxy object could not be cloned" on these — the engine must not care.
    const reactive = (obj) =>
        new Proxy(obj, {
            get(target, key) {
                const value = Reflect.get(target, key);

                return typeof value === 'object' && value !== null ? reactive(value) : value;
            },
        });

    it('crosses, uncrosses, and scores through a proxied state', () => {
        let state = cross(reactive(newGame('classic', 'solo')), 0, 0, 3);

        expect(state.players[0].rows[0].crosses).toEqual([3]);

        state = toggleExternalClose(reactive(state), 0, 1);
        state = setPenalties(reactive(state), 0, 1);
        state = uncross(reactive(state), 0, 0, 3);

        expect(state.players[0].rows[0].crosses).toEqual([]);
        expect(state.players[0].rows[1].closed).toBe(true);
        expect(total(classic, reactive(state).players[0])).toBe(-5);
    });
});

describe('scoring', () => {
    it('uses the triangular score table', () => {
        expect(SCORE_TABLE).toEqual([0, 1, 3, 6, 10, 15, 21, 28, 36, 45, 55, 66, 78]);
    });

    it('scores the rulebook example (10 + 6 + 36 + 28 - 10 = 70)', () => {
        let state = newGame('classic', 'solo');
        state = crossAll(state, 0, 0, [0, 1, 2, 3]); // 4 red -> 10
        state = crossAll(state, 0, 1, [0, 1, 2]); // 3 yellow -> 6
        state = crossAll(state, 0, 2, [0, 1, 2, 3, 4, 5, 6, 7]); // 8 green -> 36
        state = crossAll(state, 0, 3, [0, 1, 2, 3, 4, 5, 6]); // 7 blue -> 28
        state = setPenalties(state, 0, 2);

        expect(scoreByRow(classic, state.players[0])).toEqual([10, 6, 36, 28]);
        // The totals strip labels each row's score by its lock color.
        expect(scoresByLockColor(classic, state.players[0])).toEqual({ red: 10, yellow: 6, green: 36, blue: 28 });
        expect(total(classic, state.players[0])).toBe(70);
    });

    it('scores a mixed-colors row by its mark count, not by cell color', () => {
        let state = newGame('mixed-colors', 'solo');
        // Row 0: positions 0-2 yellow, 3-5 blue, 6-8 green, 9-10 red, red lock.
        // Six crosses plus the red lock earned at position 10 = 7 marks in the
        // one row. Scoring by cell color would instead scatter these into
        // yellow/blue/green/red buckets and score far lower — that was the bug.
        state = crossAll(state, 0, 0, [0, 1, 3, 6, 9, 10]);

        expect(marksByRow(mixedColors, state.players[0])).toEqual([7, 0, 0, 0]);
        expect(scoreByRow(mixedColors, state.players[0])).toEqual([SCORE_TABLE[7], 0, 0, 0]);
        expect(scoresByLockColor(mixedColors, state.players[0]).red).toBe(28);
        expect(total(mixedColors, state.players[0])).toBe(28);
    });
});

/*
 * Multiplayer is not a second rules engine: a room is an N-player state whose
 * extra slices arrive from the server, so the same functions have to hold at
 * a table of five that they hold across a shared iPad.
 */
describe('multiplayer rooms', () => {
    it('closes a locked row for every other player in the room', () => {
        let state = newGame('classic', 'multi', 4);
        state = crossAll(state, 2, 1, [0, 1, 2, 3, 4, 10]); // player 2 locks yellow

        for (const player of [0, 1, 3]) {
            expect(isRowClosedFor(state, player, 1)).toBe(true);
            expect(canCross(state, player, 1, 5)).toBe(false);
            expect(marksByRow(classic, state.players[player])[1]).toBe(0);
        }

        expect(lockedRowCount(state)).toBe(1);
        expect(isGameOver(state)).toBe(false);
    });

    it('honours the simultaneous-lock exception for every player holding five crosses', () => {
        let state = newGame('classic', 'multi', 3);
        state = crossAll(state, 1, 0, [1, 2, 3, 4, 5]); // ready to lock
        state = crossAll(state, 2, 0, [0, 1, 2]); // not ready
        state = crossAll(state, 0, 0, [0, 1, 2, 3, 4, 10]); // someone else locks red first

        expect(canCross(state, 1, 0, 10)).toBe(true);
        expect(canCross(state, 2, 0, 10)).toBe(false);

        state = cross(state, 1, 0, 10);

        expect(marksByRow(classic, state.players[1])[0]).toBe(7);
        expect(lockedRowCount(state)).toBe(1); // one row, two players locked it
    });

    it('ends the game when any player in the room takes a fourth penalty', () => {
        const state = setPenalties(newGame('classic', 'multi', 6), 4, 4);

        expect(isGameOver(state)).toBe(true);
        expect(canCross(state, 0, 0, 0)).toBe(false);
    });

    it('ends the game on two rows locked by different players', () => {
        let state = newGame('classic', 'multi', 4);
        state = crossAll(state, 0, 0, [0, 1, 2, 3, 4, 10]);
        state = crossAll(state, 3, 2, [0, 1, 2, 3, 4, 10]);

        expect(lockedRowCount(state)).toBe(2);
        expect(isGameOver(state)).toBe(true);
    });

    it('marks a row locked on paper per sheet, exactly as a 2-player game does', () => {
        let state = newGame('classic', 'multi', 3);
        state = toggleExternalClose(state, 1, 3);

        // The button records it on the sheet it belongs to — every player
        // taps their own, just as both players do on a shared iPad.
        expect(isRowClosedFor(state, 1, 3)).toBe(true);
        expect(isRowClosedFor(state, 0, 3)).toBe(false);

        // The row still counts as locked for the table, so two of them end
        // the game for everyone.
        expect(lockedRowCount(state)).toBe(1);
        expect(isGameOver(toggleExternalClose(state, 2, 0))).toBe(true);
    });
});

describe('standings', () => {
    const room = (...totals) => {
        // Build a state whose players score exactly the given totals, using
        // penalties (-5 each) and red crosses (the triangular table).
        let state = newGame('classic', 'multi', totals.length);

        totals.forEach((score, player) => {
            const marks = SCORE_TABLE.indexOf(score);

            state = crossAll(
                state,
                player,
                0,
                Array.from({ length: marks }, (_, i) => i),
            );
        });

        return state;
    };

    it('orders players best first and names the winner', () => {
        const table = standings(classic, room(6, 21, 1), ['Ada', 'Bo', 'Cy']);

        expect(table.map((p) => p.name)).toEqual(['Bo', 'Ada', 'Cy']);
        expect(table.map((p) => p.total)).toEqual([21, 6, 1]);
        expect(table.map((p) => p.rank)).toEqual([1, 2, 3]);
        expect(table.filter((p) => p.winner).map((p) => p.name)).toEqual(['Bo']);
        // The index points back at the seat, whatever the sort did.
        expect(table[0].index).toBe(1);
    });

    it('shares the top spot on a tie — Qwixx has no tie-break', () => {
        const table = standings(classic, room(10, 3, 10), ['Ada', 'Bo', 'Cy']);

        expect(table.filter((p) => p.winner).map((p) => p.name)).toEqual(['Ada', 'Cy']);
        expect(table.map((p) => p.rank)).toEqual([1, 1, 3]);
    });

    it('falls back to seat numbers when a name is missing', () => {
        expect(standings(classic, room(0, 0), ['Ada']).map((p) => p.name)).toEqual(['Ada', 'Player 2']);
    });

    it('counts penalties against the total', () => {
        const state = setPenalties(room(10, 10), 0, 2);

        expect(standings(classic, state).map((p) => p.total)).toEqual([10, 0]);
    });

    it('handles a solo game', () => {
        const table = standings(classic, newGame('classic', 'solo'), ['Ada']);

        expect(table).toEqual([{ index: 0, name: 'Ada', total: 0, rank: 1, winner: true }]);
    });
});

/*
 * A game can end on a mistap — the fourth penalty box, or the lock cell of
 * a row someone was not finished with. Taking that mark back has to put the
 * table back exactly where it was, in every mode.
 */
describe('taking back the mark that ended the game', () => {
    it('reopens play when the fourth penalty is undone', () => {
        let state = setPenalties(newGame('classic', 'duo'), 1, 4);

        expect(isGameOver(state)).toBe(true);

        state = setPenalties(state, 1, 3);

        expect(isGameOver(state)).toBe(false);
        expect(canCross(state, 0, 0, 0)).toBe(true);
        expect(canCross(state, 1, 0, 0)).toBe(true);
        expect(penaltyPoints(state.players[1])).toBe(15);
    });

    it('reopens play when the second lock is uncrossed', () => {
        let state = newGame('classic', 'duo');
        state = crossAll(state, 0, 0, [0, 1, 2, 3, 4, 10]);
        state = crossAll(state, 1, 1, [0, 1, 2, 3, 4, 10]);

        expect(isGameOver(state)).toBe(true);

        state = uncross(state, 1, 1, 10);

        expect(isGameOver(state)).toBe(false);
        expect(lockedRowCount(state)).toBe(1);
        // The row the mistake closed is open again for everyone.
        expect(isRowClosedFor(state, 0, 1)).toBe(false);
        expect(canCross(state, 0, 1, 5)).toBe(true);
        // The first lock still stands, marks and all.
        expect(marksByRow(classic, state.players[0])[0]).toBe(7);
    });

    it('reopens a room of five when one player takes their lock back', () => {
        let state = newGame('classic', 'multi', 5);
        state = crossAll(state, 2, 0, [0, 1, 2, 3, 4, 10]);
        state = crossAll(state, 4, 3, [0, 1, 2, 3, 4, 10]);

        expect(isGameOver(state)).toBe(true);

        // Player 4 mistapped: everyone's game resumes once it is undone.
        state = uncross(state, 4, 3, 10);

        expect(isGameOver(state)).toBe(false);

        for (let player = 0; player < 5; player++) {
            expect(isRowClosedFor(state, player, 3)).toBe(false);
        }
    });

    it('leaves an undone penalty out of the score', () => {
        let state = setPenalties(newGame('classic', 'duo'), 0, 4);
        state = setPenalties(state, 0, 3);

        expect(total(classic, state.players[0])).toBe(-15);
    });
});
