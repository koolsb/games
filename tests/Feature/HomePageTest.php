<?php

declare(strict_types=1);

it('lists every registered game on the root menu', function (): void {
    $response = $this->get('/');

    $response->assertOk();

    foreach (config('games.games') as $game) {
        $response->assertSee($game['name'])->assertSee(route($game['route']), false);
    }
});

/*
| The whole point of the merge: no game is a dead end. Each landing page
| carries the shared header's "All games" control back to the menu.
*/
it('gives every game landing page a way back to the menu', function (string $route): void {
    $this->get(route($route))
        ->assertOk()
        ->assertSee('All games');
})->with(['qwixx.picker', 'phase10.generator']);
