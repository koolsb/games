<?php

declare(strict_types=1);

it('renders the landing page with the shared header', function (): void {
    $this->get('/scoreboard')
        ->assertOk()
        ->assertSee('Keep score for any game')
        ->assertSee('Follow a game')
        ->assertSee('All games')
        ->assertDontSee('>Rules<', false);
});

it('renders the play screen for this device’s current game', function (): void {
    $this->get('/scoreboard/play')
        ->assertOk()
        ->assertSee('scoreboardGame(null, null)', false);
});

it('embeds the server snapshot for followers', function (): void {
    $code = $this->postJson('/scoreboard/games', ['state' => [
        'title' => 'Darts', 'round' => 1, 'rev' => 1,
        'players' => [['id' => 'p1', 'name' => 'Ada']], 'entries' => [],
    ]])->json('board.code');

    $this->get("/scoreboard/play/$code")
        ->assertOk()
        ->assertSee('Darts');
});

/*
| The scorekeeper may reopen their own game offline or after the server
| copy expired, so an unknown-but-valid code still renders the screen.
*/
it('renders the play screen for a code the server does not know', function (): void {
    $this->get('/scoreboard/play/ZZZZ')->assertOk();
});

it('redirects a sloppy code to the canonical one', function (): void {
    $this->get('/scoreboard/play/ab-cd')->assertRedirect('/scoreboard/play/ABCD');
});

it('404s on a code that can never be valid', function (): void {
    $this->get('/scoreboard/play/ABC1')->assertNotFound();
});
