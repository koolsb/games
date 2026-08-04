<?php

declare(strict_types=1);

use App\Support\Phase\ScoreRoom;
use Illuminate\Support\Str;

function makeRoom(int $phaseCount, array $playerNames): ScoreRoom
{
    $phases = array_map(static fn (int $i): string => "phase-{$i}", range(1, $phaseCount));

    return ScoreRoom::open(code: 'ABCD', hostToken: Str::random(40), phaseSignatures: $phases, playerNames: $playerNames);
}

it('tracks total score and phase progress across rounds', function (): void {
    $room = makeRoom(3, ['Alice', 'Bob']);
    [$alice, $bob] = $room->players;

    $room = $room->addRound([
        $alice->id => ['score' => 15, 'completed' => true],
        $bob->id => ['score' => 40, 'completed' => false],
    ]);

    [$alice, $bob] = $room->players;

    expect($alice->totalScore())->toBe(15)
        ->and($alice->currentPhase(3))->toBe(2)
        ->and($bob->totalScore())->toBe(40)
        ->and($bob->currentPhase(3))->toBe(1)
        ->and($room->status)->toBe(ScoreRoom::ACTIVE);
});

it('ends the game the moment one player finishes every phase', function (): void {
    $room = makeRoom(2, ['Alice', 'Bob']);
    [$alice, $bob] = $room->players;

    $room = $room->addRound([
        $alice->id => ['score' => 0, 'completed' => true],
        $bob->id => ['score' => 20, 'completed' => false],
    ]);
    [$alice, $bob] = $room->players;

    $room = $room->addRound([
        $alice->id => ['score' => 0, 'completed' => true],
        $bob->id => ['score' => 10, 'completed' => false],
    ]);
    [$alice, $bob] = $room->players;

    expect($room->status)->toBe(ScoreRoom::ENDED)
        ->and($room->winnerIds)->toBe([$alice->id]);
});

it('does not end the game while no one has finished every phase', function (): void {
    $room = makeRoom(5, ['Alice', 'Bob']);
    [$alice, $bob] = $room->players;

    $room = $room->addRound([
        $alice->id => ['score' => 10, 'completed' => true],
        $bob->id => ['score' => 5, 'completed' => true],
    ]);

    expect($room->status)->toBe(ScoreRoom::ACTIVE)
        ->and($room->winnerIds)->toBe([]);
});

it('awards the lowest score when two players finish in the same round', function (): void {
    $room = makeRoom(1, ['Alice', 'Bob', 'Carol']);
    [$alice, $bob, $carol] = $room->players;

    $room = $room->addRound([
        $alice->id => ['score' => 30, 'completed' => true],
        $bob->id => ['score' => 10, 'completed' => true],
        $carol->id => ['score' => 0, 'completed' => false],
    ]);

    expect($room->status)->toBe(ScoreRoom::ENDED)
        ->and($room->winnerIds)->toBe([$bob->id]);
});

it('enters a tiebreak when the finishers scored exactly the same', function (): void {
    $room = makeRoom(1, ['Alice', 'Bob']);
    [$alice, $bob] = $room->players;

    $room = $room->addRound([
        $alice->id => ['score' => 20, 'completed' => true],
        $bob->id => ['score' => 20, 'completed' => true],
    ]);

    expect($room->status)->toBe(ScoreRoom::TIEBREAK)
        ->and($room->tiedPlayerIds)->toEqualCanonicalizing([$alice->id, $bob->id])
        ->and($room->winnerIds)->toBe([]);
});

it('resolves a tiebreak into a single winner', function (): void {
    $room = makeRoom(1, ['Alice', 'Bob']);
    [$alice, $bob] = $room->players;

    $room = $room->addRound([
        $alice->id => ['score' => 20, 'completed' => true],
        $bob->id => ['score' => 20, 'completed' => true],
    ]);

    $room = $room->resolveTiebreak($bob->id);

    expect($room->status)->toBe(ScoreRoom::ENDED)
        ->and($room->winnerIds)->toBe([$bob->id])
        ->and($room->tiedPlayerIds)->toBe([]);
});

it('rejects resolving a tiebreak with a player who was not tied', function (): void {
    $room = makeRoom(1, ['Alice', 'Bob', 'Carol']);
    [$alice, $bob, $carol] = $room->players;

    $room = $room->addRound([
        $alice->id => ['score' => 20, 'completed' => true],
        $bob->id => ['score' => 20, 'completed' => true],
        $carol->id => ['score' => 5, 'completed' => false],
    ]);

    expect(fn () => $room->resolveTiebreak($carol->id))->toThrow(InvalidArgumentException::class);
});

it('can undo the last round, including walking a finished game back to active', function (): void {
    $room = makeRoom(1, ['Alice', 'Bob']);
    [$alice, $bob] = $room->players;

    $room = $room->addRound([
        $alice->id => ['score' => 0, 'completed' => true],
        $bob->id => ['score' => 50, 'completed' => false],
    ]);

    expect($room->status)->toBe(ScoreRoom::ENDED);

    $room = $room->undoLastRound();

    expect($room->status)->toBe(ScoreRoom::ACTIVE)
        ->and($room->winnerIds)->toBe([])
        ->and($room->players[0]->totalScore())->toBe(0)
        ->and($room->players[0]->phasesCompleted())->toBe(0);
});

it('rejects a round that does not include every player', function (): void {
    $room = makeRoom(3, ['Alice', 'Bob']);
    [$alice] = $room->players;

    expect(fn () => $room->addRound([$alice->id => ['score' => 10, 'completed' => false]]))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects adding a round to a game that is already over', function (): void {
    $room = makeRoom(1, ['Alice', 'Bob']);
    [$alice, $bob] = $room->players;

    $room = $room->addRound([
        $alice->id => ['score' => 0, 'completed' => true],
        $bob->id => ['score' => 50, 'completed' => false],
    ]);

    expect(fn () => $room->addRound([
        $alice->id => ['score' => 0, 'completed' => false],
        $bob->id => ['score' => 0, 'completed' => false],
    ]))->toThrow(RuntimeException::class);
});

it('adapts to a phase count other than ten', function (): void {
    $room = makeRoom(15, ['Alice']);
    [$alice] = $room->players;

    foreach (range(1, 14) as $ignored) {
        $room = $room->addRound([$alice->id => ['score' => 5, 'completed' => true]]);
    }

    expect($room->status)->toBe(ScoreRoom::ACTIVE)
        ->and($room->players[0]->currentPhase(15))->toBe(15);

    $room = $room->addRound([$alice->id => ['score' => 0, 'completed' => true]]);

    expect($room->status)->toBe(ScoreRoom::ENDED)
        ->and($room->winnerIds)->toBe([$alice->id]);
});
