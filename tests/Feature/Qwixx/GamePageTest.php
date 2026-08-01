<?php

declare(strict_types=1);

it('renders the game page for every layout and mode', function (string $layout, string $mode) {
    $this->get("/qwixx/play/$layout/$mode")->assertOk()->assertSee('qwixxGame', false);
})->with(['classic', 'mixed-numbers', 'mixed-colors'])->with(['solo', 'duo']);

it('defaults to solo mode', function () {
    $this->get('/qwixx/play/classic')->assertOk();
});

it('404s on unknown layouts and modes', function () {
    $this->get('/qwixx/play/unknown/solo')->assertNotFound();
    $this->get('/qwixx/play/classic/trio')->assertNotFound();
});

it('embeds the layout cells for the engine', function () {
    // Mixed-numbers red row starts with 10 — proves the client payload
    // carries the transcribed order. @js() unicode-escapes double quotes
    // inside its JSON.parse() payload.
    $this->get('/qwixx/play/mixed-numbers/solo')
        ->assertOk()
        ->assertSee('\\u0022n\\u0022:10', false);
});

it('renders one sheet for solo and two rotated-paired sheets for duo', function () {
    $solo = $this->get('/qwixx/play/classic/solo');
    $solo->assertOk();
    expect(substr_count($solo->content(), 'qx-sheet'))->toBe(1)
        ->and($solo->content())->not->toContain('rotate-180');

    $duo = $this->get('/qwixx/play/classic/duo');
    $duo->assertOk();
    expect(substr_count($duo->content(), 'qx-sheet'))->toBe(2)
        ->and($duo->content())->toContain('rotate-180');
});

it('includes the reset confirmation modal', function () {
    $this->get('/qwixx/play/classic/solo')
        ->assertOk()
        ->assertSee('Start a new game?')
        ->assertSee('resetGame()', false);
});
