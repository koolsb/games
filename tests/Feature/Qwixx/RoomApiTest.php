<?php

declare(strict_types=1);

use App\Services\Qwixx\RoomStore;
use App\Support\Qwixx\Room;

/**
 * Hosts a room and returns [code, host token].
 *
 * @return array{0: string, 1: string}
 */
function hostRoom(string $name = 'Ada'): array
{
    $response = test()->postJson('/qwixx/rooms', ['layout' => 'classic', 'name' => $name]);

    $response->assertCreated();

    return [$response->json('room.code'), $response->json('token')];
}

function joinRoom(string $code, string $name): string
{
    $response = test()->postJson("/qwixx/rooms/$code/join", ['name' => $name]);

    $response->assertOk();

    return $response->json('token');
}

/** A valid player slice: three crosses in the red row. */
function playerSheet(int $penalties = 0): array
{
    return [
        'rows' => array_fill(0, 4, ['crosses' => [0, 1, 2], 'locked' => false, 'closed' => false]),
        'penalties' => $penalties,
    ];
}

it('hosts a game and seats the host', function () {
    $response = $this->postJson('/qwixx/rooms', ['layout' => 'classic', 'name' => 'Ada']);

    $response->assertCreated()
        ->assertJsonPath('room.layout', 'classic')
        ->assertJsonPath('room.status', Room::LOBBY)
        ->assertJsonPath('room.players.0.name', 'Ada')
        ->assertJsonPath('room.players.0.isHost', true);

    expect($response->json('room.code'))->toHaveLength(4)
        ->and($response->json('token'))->not->toBeEmpty()
        ->and($response->json('playerId'))->toBe($response->json('room.players.0.id'));
});

it('never exposes another player token', function () {
    [$code] = hostRoom();
    joinRoom($code, 'Bo');

    $this->getJson("/qwixx/rooms/$code")
        ->assertOk()
        ->assertJsonMissingPath('room.players.0.token')
        ->assertJsonMissingPath('room.players.1.token');
});

it('names an unnamed player after their seat', function () {
    [$code] = hostRoom(' ');

    $this->postJson("/qwixx/rooms/$code/join", ['name' => null])->assertOk();

    $this->getJson("/qwixx/rooms/$code")
        ->assertJsonPath('room.players.0.name', 'Player 1')
        ->assertJsonPath('room.players.1.name', 'Player 2');
});

it('refuses a layout that does not exist', function () {
    $this->postJson('/qwixx/rooms', ['layout' => 'nope', 'name' => 'Ada'])
        ->assertStatus(422)
        ->assertJsonPath('reason', 'unknown_layout');
});

it('finds a room however the code was typed', function () {
    [$code] = hostRoom();

    $this->getJson('/qwixx/rooms/'.strtolower($code))->assertOk()->assertJsonPath('room.code', $code);
});

it('404s on a code that is not active', function () {
    $this->getJson('/qwixx/rooms/ZZZZ')->assertNotFound()->assertJsonPath('reason', 'not_found');
    $this->getJson('/qwixx/rooms/NOPE!')->assertNotFound();
    $this->postJson('/qwixx/rooms/ZZZZ/join', ['name' => 'Bo'])->assertNotFound();
});

it('seats a player who joins', function () {
    [$code] = hostRoom();

    $this->postJson("/qwixx/rooms/$code/join", ['name' => 'Bo'])
        ->assertOk()
        ->assertJsonPath('room.players.1.name', 'Bo')
        ->assertJsonPath('room.players.1.isHost', false)
        ->assertJsonCount(2, 'room.players');
});

it('turns away a player once the game has started', function () {
    [$code, $host] = hostRoom();

    $this->withHeader('X-Qwixx-Token', $host)->postJson("/qwixx/rooms/$code/start")->assertOk();

    $this->postJson("/qwixx/rooms/$code/join", ['name' => 'Late'])
        ->assertStatus(409)
        ->assertJsonPath('reason', 'already_started');
});

it('turns away a player once the room is full', function () {
    config(['qwixx.multiplayer.max_players' => 2]);

    [$code] = hostRoom();
    joinRoom($code, 'Bo');

    $this->postJson("/qwixx/rooms/$code/join", ['name' => 'Cy'])
        ->assertStatus(409)
        ->assertJsonPath('reason', 'full');
});

it('lets the host start the game and nobody else', function () {
    [$code, $host] = hostRoom();
    $guest = joinRoom($code, 'Bo');

    $this->withHeader('X-Qwixx-Token', $guest)->postJson("/qwixx/rooms/$code/start")
        ->assertForbidden()
        ->assertJsonPath('reason', 'not_host');

    $this->withHeader('X-Qwixx-Token', $host)->postJson("/qwixx/rooms/$code/start")
        ->assertOk()
        ->assertJsonPath('room.status', Room::PLAYING);
});

it('rejects a sync without a seat', function () {
    [$code] = hostRoom();

    $this->postJson("/qwixx/rooms/$code/sync", ['state' => playerSheet()])
        ->assertForbidden()
        ->assertJsonPath('reason', 'not_seated');

    $this->withHeader('X-Qwixx-Token', 'made-up')->postJson("/qwixx/rooms/$code/sync", ['state' => playerSheet()])
        ->assertForbidden()
        ->assertJsonPath('reason', 'not_seated');
});

it('stores the caller sheet and hands back everyone else', function () {
    [$code, $host] = hostRoom();
    $guest = joinRoom($code, 'Bo');

    $this->withHeader('X-Qwixx-Token', $host)
        ->postJson("/qwixx/rooms/$code/sync", ['state' => playerSheet(2)])
        ->assertOk()
        ->assertJsonPath('room.players.0.state.penalties', 2);

    // The guest's poll carries their own sheet up and the host's back down.
    $this->withHeader('X-Qwixx-Token', $guest)
        ->postJson("/qwixx/rooms/$code/sync", ['state' => playerSheet(1)])
        ->assertOk()
        ->assertJsonPath('room.players.0.state.penalties', 2)
        ->assertJsonPath('room.players.1.state.penalties', 1);
});

it('writes only the caller own seat', function () {
    [$code, $host] = hostRoom();
    $guest = joinRoom($code, 'Bo');

    $this->withHeader('X-Qwixx-Token', $guest)->postJson("/qwixx/rooms/$code/sync", ['state' => playerSheet(3)]);

    $room = app(RoomStore::class)->find($code);

    expect($room->players[0]->state)->toBe([])
        ->and($room->players[1]->state['penalties'])->toBe(3);

    // And the host's own sync leaves the guest's sheet alone.
    $this->withHeader('X-Qwixx-Token', $host)->postJson("/qwixx/rooms/$code/sync", ['state' => playerSheet()]);

    expect(app(RoomStore::class)->find($code)->players[1]->state['penalties'])->toBe(3);
});

it('refuses a sheet that is not a scoresheet', function () {
    [$code, $host] = hostRoom();

    $bad = playerSheet();
    $bad['rows'][0]['crosses'] = [0, 99];

    $this->withHeader('X-Qwixx-Token', $host)
        ->postJson("/qwixx/rooms/$code/sync", ['state' => $bad])
        ->assertStatus(422);

    $this->withHeader('X-Qwixx-Token', $host)
        ->postJson("/qwixx/rooms/$code/sync", ['state' => ['rows' => [], 'penalties' => 0]])
        ->assertStatus(422);
});

it('accepts a sync with no sheet as a plain poll', function () {
    [$code, $host] = hostRoom();

    $this->withHeader('X-Qwixx-Token', $host)->postJson("/qwixx/rooms/$code/sync")->assertOk();
});

it('ends the game for everyone once one browser reports it, and keeps it ended', function () {
    [$code, $host] = hostRoom();
    $guest = joinRoom($code, 'Bo');

    $this->withHeader('X-Qwixx-Token', $host)->postJson("/qwixx/rooms/$code/start")->assertOk();

    $this->withHeader('X-Qwixx-Token', $guest)
        ->postJson("/qwixx/rooms/$code/sync", ['state' => playerSheet(), 'ended' => true])
        ->assertOk()
        ->assertJsonPath('room.status', Room::ENDED);

    // A device that reloads afterwards still lands on the results.
    $this->getJson("/qwixx/rooms/$code")->assertJsonPath('room.status', Room::ENDED);

    $ended = app(RoomStore::class)->find($code)->endedAt;

    // A later sync must not move the finish line.
    $this->withHeader('X-Qwixx-Token', $host)
        ->postJson("/qwixx/rooms/$code/sync", ['state' => playerSheet(), 'ended' => true])
        ->assertJsonPath('room.status', Room::ENDED);

    expect(app(RoomStore::class)->find($code)->endedAt)->toBe($ended);
});

it('lets the host deal another game, clearing every sheet', function () {
    [$code, $host] = hostRoom();
    $guest = joinRoom($code, 'Bo');

    $this->withHeader('X-Qwixx-Token', $host)->postJson("/qwixx/rooms/$code/start");
    $this->withHeader('X-Qwixx-Token', $guest)
        ->postJson("/qwixx/rooms/$code/sync", ['state' => playerSheet(), 'ended' => true]);

    $this->withHeader('X-Qwixx-Token', $guest)->postJson("/qwixx/rooms/$code/restart")
        ->assertForbidden()
        ->assertJsonPath('reason', 'not_host');

    $this->withHeader('X-Qwixx-Token', $host)->postJson("/qwixx/rooms/$code/restart")
        ->assertOk()
        ->assertJsonPath('room.status', Room::PLAYING)
        ->assertJsonPath('room.round', 2)
        ->assertJsonPath('room.endedAt', null)
        ->assertJsonPath('room.players.1.state', null)
        // Everyone keeps their seat, so nobody has to rejoin.
        ->assertJsonCount(2, 'room.players');

    // The guest's token still works after the restart.
    $this->withHeader('X-Qwixx-Token', $guest)->postJson("/qwixx/rooms/$code/sync")->assertOk();
});

it('trims a name to something a scoreboard can hold', function () {
    $response = $this->postJson('/qwixx/rooms', [
        'layout' => 'classic',
        'name' => str_repeat('a', 40),
    ]);

    $response->assertStatus(422);

    $this->postJson('/qwixx/rooms', ['layout' => 'classic', 'name' => "A\u{0000}da"])
        ->assertCreated()
        ->assertJsonPath('room.players.0.name', 'Ada');
});
