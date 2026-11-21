<?php

declare(strict_types=1);

use App\Services\Scoreboard\BoardStore;

/**
 * A game exactly as the browser sends it — no entries by default, because
 * that is what every game's first push carries, and `required` on an
 * empty array is the kind of bug that only shows up there.
 *
 * @return array<string, mixed>
 */
function boardState(int $rev = 1, array $entries = [], array $overrides = []): array
{
    return [
        'title' => 'Rummy',
        'lowestWins' => false,
        'round' => 1,
        'rev' => $rev,
        'players' => [
            ['id' => 'p1', 'name' => 'Ada'],
            ['id' => 'p2', 'name' => 'Bea'],
        ],
        'entries' => $entries,
        ...$overrides,
    ];
}

function entry(string $id, string $playerId, int $points, int $round = 1, string $kind = 'add'): array
{
    return ['id' => $id, 'batch' => $id, 'kind' => $kind, 'playerId' => $playerId, 'points' => $points, 'round' => $round, 'at' => 1];
}

/** @return array{0: string, 1: string} [code, token] */
function registerBoard(array $state = []): array
{
    $response = test()->postJson('/scoreboard/games', ['state' => $state ?: boardState()]);

    $response->assertCreated();

    return [$response->json('board.code'), $response->json('token')];
}

it('registers a new game and hands back a code and token', function (): void {
    $response = $this->postJson('/scoreboard/games', ['state' => boardState()]);

    $response->assertCreated()
        ->assertJsonPath('board.state.title', 'Rummy')
        ->assertJsonPath('board.state.players.1.name', 'Bea')
        ->assertJsonPath('board.state.entries', [])
        ->assertJsonPath('board.rev', 1);

    expect($response->json('board.code'))->toMatch('/^[A-HJ-NP-Z2-7]{4}$/')
        ->and($response->json('token'))->toHaveLength(40)
        ->and($response->json('board'))->not->toHaveKey('host_token');
});

it('lets anyone follow a game without the token', function (): void {
    [$code] = registerBoard();

    $this->getJson("/scoreboard/games/$code")
        ->assertOk()
        ->assertJsonPath('board.code', $code)
        ->assertJsonPath('board.state.players.0.name', 'Ada')
        ->assertJsonMissingPath('token');
});

it('finds a game from a lowercased code', function (): void {
    [$code] = registerBoard();

    $this->getJson('/scoreboard/games/'.strtolower($code))->assertOk();
});

it('404s on an unknown or malformed code', function (string $code): void {
    $this->getJson("/scoreboard/games/$code")
        ->assertNotFound()
        ->assertJsonPath('reason', 'not_found');
})->with(['ZZZZ', 'nope!', 'AB']);

it('accepts a newer revision from the scorekeeper', function (): void {
    [$code, $token] = registerBoard();

    $this->putJson("/scoreboard/games/$code", ['state' => boardState(2, [entry('e1', 'p1', 5)])], ['X-Scoreboard-Token' => $token])
        ->assertOk()
        ->assertJsonPath('accepted', true)
        ->assertJsonPath('board.rev', 2)
        ->assertJsonPath('board.state.entries.0.points', 5);
});

it('ignores a stale or repeated revision without rolling the game back', function (): void {
    [$code, $token] = registerBoard();
    $headers = ['X-Scoreboard-Token' => $token];

    $this->putJson("/scoreboard/games/$code", ['state' => boardState(3, [entry('e1', 'p1', 5)])], $headers)->assertOk();

    // A late push of an older revision, then a retry of the current one.
    foreach ([2, 3] as $rev) {
        $this->putJson("/scoreboard/games/$code", ['state' => boardState($rev)], $headers)
            ->assertOk()
            ->assertJsonPath('accepted', false)
            ->assertJsonPath('board.rev', 3)
            ->assertJsonPath('board.state.entries.0.id', 'e1');
    }
});

it('refuses writes without the scorekeeper token', function (?string $token): void {
    [$code] = registerBoard();

    $this->putJson("/scoreboard/games/$code", ['state' => boardState(2)], $token ? ['X-Scoreboard-Token' => $token] : [])
        ->assertForbidden()
        ->assertJsonPath('reason', 'not_host');

    expect(app(BoardStore::class)->find($code)->rev)->toBe(1);
})->with([null, 'wrong-token']);

it('lets a scorekeeper reclaim the code of a game the server forgot', function (): void {
    $token = str_repeat('a', 40);

    $response = $this->postJson('/scoreboard/games', ['state' => boardState(7), 'code' => 'abcd', 'token' => $token]);

    $response->assertCreated()
        ->assertJsonPath('board.code', 'ABCD')
        ->assertJsonPath('token', $token);
});

it('hands out a fresh code when the reclaimed one is taken', function (): void {
    [$code] = registerBoard();

    $response = $this->postJson('/scoreboard/games', ['state' => boardState(), 'code' => $code, 'token' => str_repeat('b', 40)]);

    $response->assertCreated();
    expect($response->json('board.code'))->not->toBe($code);
});

it('rebuilds the game, dropping unknown fields and orphan entries', function (): void {
    $state = boardState(1, [
        entry('e1', 'p1', 5) + ['sneaky' => 'x'],
        entry('e2', 'ghost', 9),
    ]);
    $state['players'][1]['name'] = "  \u{200B} ";
    $state['extra'] = 'nope';

    $response = $this->postJson('/scoreboard/games', ['state' => $state])->assertCreated();

    expect($response->json('board.state'))->not->toHaveKey('extra')
        ->and($response->json('board.state.entries'))->toHaveCount(1)
        ->and($response->json('board.state.entries.0'))->not->toHaveKey('sneaky')
        ->and($response->json('board.state.players.1.name'))->toBe('Player 2');
});

it('validates the shape of a pushed game', function (array $state): void {
    $this->postJson('/scoreboard/games', ['state' => $state])->assertUnprocessable();
})->with([
    'no players' => [boardState(overrides: ['players' => []])],
    'too many players' => [boardState(overrides: ['players' => array_map(fn ($i) => ['id' => "p$i", 'name' => "P$i"], range(1, 13))])],
    'duplicate player ids' => [boardState(overrides: ['players' => [['id' => 'p1', 'name' => 'A'], ['id' => 'p1', 'name' => 'B']]])],
    'long name' => [boardState(overrides: ['players' => [['id' => 'p1', 'name' => str_repeat('x', 21)]]])],
    'fractional points' => [boardState(1, [['id' => 'e', 'batch' => 'e', 'playerId' => 'p1', 'points' => 1.5, 'round' => 1]])],
    'huge points' => [boardState(1, [entry('e', 'p1', 2000000)])],
    'round zero' => [boardState(overrides: ['round' => 0])],
    'missing entries' => [array_diff_key(boardState(), ['entries' => true])],
]);
