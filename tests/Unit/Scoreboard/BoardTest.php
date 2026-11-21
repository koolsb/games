<?php

declare(strict_types=1);

use App\Support\Scoreboard\Board;

it('takes its revision from the game it opens with', function (): void {
    $board = Board::open('ABCD', 'secret', ['rev' => 4]);

    expect($board->rev)->toBe(4)
        ->and($board->accepts(4))->toBeFalse()
        ->and($board->accepts(5))->toBeTrue();
});

it('checks the scorekeeper token', function (): void {
    $board = Board::open('ABCD', 'secret', ['rev' => 1]);

    expect($board->isHost('secret'))->toBeTrue()
        ->and($board->isHost('nope'))->toBeFalse()
        ->and($board->isHost(''))->toBeFalse()
        ->and($board->isHost(null))->toBeFalse();
});

it('never puts the token in what browsers see', function (): void {
    $client = Board::open('ABCD', 'secret', ['rev' => 1])->toClientArray();

    expect($client)->toHaveKeys(['code', 'rev', 'updatedAt', 'state'])
        ->and(json_encode($client))->not->toContain('secret');
});

it('round-trips through the cache shape', function (): void {
    $board = Board::open('ABCD', 'secret', ['rev' => 2, 'players' => []])->withState(['rev' => 3, 'players' => []]);

    $copy = Board::fromArray($board->toArray());

    expect($copy->code)->toBe('ABCD')
        ->and($copy->hostToken)->toBe('secret')
        ->and($copy->rev)->toBe(3)
        ->and($copy->state)->toBe(['rev' => 3, 'players' => []]);
});
