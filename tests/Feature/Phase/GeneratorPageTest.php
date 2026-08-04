<?php

declare(strict_types=1);

use Livewire\Livewire;

it('renders the generator page', function (): void {
    $this->get('/phase10')
        ->assertOk()
        ->assertSee('Generate a Phase 10 game');
});

it('generates a full game of ten phases', function (): void {
    $component = Livewire::test('phase10::generator')->call('generate');

    expect($component->get('signatures'))->toHaveCount(10)
        ->and($component->get('generated'))->toBeTrue();
});

it('shows the flat band picker when flat mode is selected', function (): void {
    Livewire::test('phase10::generator')
        ->set('mode', 'flat')
        ->assertSee('Band for every phase');
});

it('regenerates a single slot without disturbing the others', function (): void {
    $component = Livewire::test('phase10::generator')->call('generate');
    $before = $component->get('signatures');

    $component->call('regenerate', 2);
    $after = $component->get('signatures');

    expect($after)->toHaveCount(10)
        ->and($after[2])->not->toBe($before[2])
        ->and($after[0])->toBe($before[0])
        ->and($after[9])->toBe($before[9]);
});

it('removes a phase from the game', function (): void {
    $component = Livewire::test('phase10::generator')->call('generate');

    $component->call('remove', 0);

    expect($component->get('signatures'))->toHaveCount(9);
});

it('loads the standard 10 phases', function (): void {
    $component = Livewire::test('phase10::generator')->call('useClassics');

    expect($component->get('signatures'))->toHaveCount(10)
        ->and($component->get('generated'))->toBeTrue();
});

it('adds a single phase with generate one', function (): void {
    $component = Livewire::test('phase10::generator')
        ->call('clear')
        ->call('generateOne');

    expect($component->get('signatures'))->toHaveCount(1);
});
