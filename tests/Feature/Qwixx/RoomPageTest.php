<?php

declare(strict_types=1);

use App\Services\Qwixx\RoomStore;

it('renders a room, embedding the layout and the room for the client', function () {
    $room = app(RoomStore::class)->create('mixed-numbers', 'Ada');

    $response = $this->get("/qwixx/room/{$room->code}");

    $response->assertOk()
        ->assertSee('qwixxGame', false)
        ->assertSee($room->code)
        // The page carries the same layout payload the play screen does, so
        // a joiner needs no second request to draw the sheet.
        ->assertSee('\\u0022n\\u0022:10', false)
        ->assertSee('Mixed Numbers');
});

it('never puts a player token in the page', function () {
    $room = app(RoomStore::class)->create('classic', 'Ada');

    $this->get("/qwixx/room/{$room->code}")->assertOk()->assertDontSee($room->players[0]->token);
});

it('finds a room however the code was typed', function () {
    $room = app(RoomStore::class)->create('classic', 'Ada');

    $this->get('/qwixx/room/'.strtolower($room->code))->assertOk();
});

it('404s once a room has expired or never existed', function () {
    $this->get('/qwixx/room/ZZZZ')->assertNotFound();
    $this->get('/qwixx/room/TOOLONG')->assertNotFound();
    $this->get('/qwixx/room/AB1D')->assertNotFound();
});
