<?php

declare(strict_types=1);

use App\Services\Qwixx\RoomStore;
use App\Support\Qwixx\Room;
use App\Support\Qwixx\RoomCode;
use App\Support\Qwixx\RoomPlayer;

beforeEach(function () {
    $this->store = app(RoomStore::class);
});

it('opens a room on a fresh code with the host seated', function () {
    $room = $this->store->create('classic', 'Ada');

    expect(RoomCode::isValid($room->code))->toBeTrue()
        ->and($room->layoutId)->toBe('classic')
        ->and($room->status)->toBe(Room::LOBBY)
        ->and($room->players)->toHaveCount(1)
        ->and($room->players[0]->name)->toBe('Ada')
        ->and($room->players[0]->isHost)->toBeTrue()
        ->and($room->players[0]->token)->not->toBeEmpty();
});

it('reads a room back through the cache unchanged', function () {
    $created = $this->store->create('mixed-colors', 'Ada');
    $found = $this->store->find($created->code);

    expect($found->toArray())->toEqual($created->toArray());
});

it('returns nothing for a code that was never used', function () {
    expect($this->store->find('ZZZZ'))->toBeNull();
});

it('bumps the version on every write so clients can spot a stale poll', function () {
    $room = $this->store->create('classic', 'Ada');

    $updated = $this->store->mutate($room->code, fn (Room $current): Room => $current->join(RoomPlayer::make('Bo', false)));

    expect($updated->version)->toBe($room->version + 1)
        ->and($updated->players)->toHaveCount(2)
        ->and($this->store->find($room->code)->players)->toHaveCount(2);
});

it('leaves the room alone when a mutation declines to change it', function () {
    $room = $this->store->create('classic', 'Ada');

    $result = $this->store->mutate($room->code, fn (): ?Room => null);

    expect($result->version)->toBe($room->version)
        ->and($result->players)->toHaveCount(1);
});

it('does nothing for a code that has expired', function () {
    expect($this->store->mutate('ZZZZ', fn (Room $room): Room => $room))->toBeNull();
});

it('stores a player sheet as handed over and hands it back to everyone', function () {
    $sheet = [
        'rows' => array_fill(0, 4, ['crosses' => [0, 1, 2], 'locked' => false, 'closed' => false]),
        'penalties' => 1,
    ];

    $room = $this->store->create('classic', 'Ada');
    $updated = $this->store->mutate(
        $room->code,
        fn (Room $current): Room => $current->replace($current->players[0]->withState($sheet)),
    );

    expect($updated->players[0]->state)->toEqual($sheet)
        ->and($updated->toClientArray()['players'][0]['state'])->toEqual($sheet);
});

it('never sends a player token to the client', function () {
    $room = $this->store->create('classic', 'Ada');

    expect(json_encode($room->toClientArray()))->not->toContain($room->players[0]->token);
});

it('forgets a room on request', function () {
    $room = $this->store->create('classic', 'Ada');

    $this->store->forget($room->code);

    expect($this->store->find($room->code))->toBeNull();
});

it('reports fullness against the configured maximum', function () {
    $room = $this->store->create('classic', 'Ada');

    expect($room->isFull(1))->toBeTrue()
        ->and($room->isFull($this->store->maxPlayers()))->toBeFalse();
});

it('clears every sheet and bumps the round when the host deals again', function () {
    $sheet = ['rows' => array_fill(0, 4, ['crosses' => [0], 'locked' => false, 'closed' => false]), 'penalties' => 0];

    $room = $this->store->create('classic', 'Ada');
    $room = $room->join(RoomPlayer::make('Bo', false));
    $room = $room->replace($room->players[1]->withState($sheet));
    $this->store->put($room);

    $restarted = $this->store->mutate($room->code, fn (Room $current): Room => $current->restarted());

    expect($restarted->round)->toBe($room->round + 1)
        ->and($restarted->status)->toBe(Room::PLAYING)
        ->and($restarted->players)->toHaveCount(2)
        ->and($restarted->players[1]->state)->toBe([])
        // Seats survive: everyone keeps their token and their name.
        ->and($restarted->players[1]->token)->toBe($room->players[1]->token)
        ->and($restarted->players[1]->name)->toBe('Bo');
});
