<?php

declare(strict_types=1);

it('renders the picker with all three layouts', function () {
    $response = $this->get('/qwixx');

    $response->assertOk()
        ->assertSee('Classic')
        ->assertSee('Mixed Numbers')
        ->assertSee('Mixed Colors');
});

it('links every layout to its solo and duo game routes', function () {
    $response = $this->get('/qwixx');

    foreach (['classic', 'mixed-numbers', 'mixed-colors'] as $id) {
        $response->assertSee("/qwixx/play/$id/solo")->assertSee("/qwixx/play/$id/duo");
    }
});

it('offers to host a multiplayer game from every layout, and to join one', function () {
    $response = $this->get('/qwixx');

    $response->assertOk()
        ->assertSee('Multiplayer')
        ->assertSee('Host a game')
        ->assertSee('Join a game')
        ->assertSee('qwixxLauncher', false);

    foreach (['classic', 'mixed-numbers', 'mixed-colors'] as $id) {
        // Each card hands its own layout to the host dialog — @js() renders
        // a single-quoted JS string.
        $response->assertSee("hostLayout = '$id'", false);
    }
});
