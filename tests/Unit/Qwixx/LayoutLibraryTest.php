<?php

declare(strict_types=1);

use App\Enums\Qwixx\Color;
use App\Services\Qwixx\LayoutLibrary;
use App\Support\Qwixx\Layout;

function library(): LayoutLibrary
{
    return new LayoutLibrary(require dirname(__DIR__, 3).'/config/qwixx.php');
}

it('loads the three shipped layouts from config', function () {
    expect(library()->all()->keys()->all())->toBe(['classic', 'mixed-numbers', 'mixed-colors']);
});

it('finds the classic layout with its canonical row orders', function () {
    $classic = library()->find('classic');

    expect($classic)->toBeInstanceOf(Layout::class)
        ->and($classic->rows[0]->lockColor)->toBe(Color::Red)
        ->and(array_map(fn ($c) => $c->number, $classic->rows[0]->cells))->toBe(range(2, 12))
        ->and($classic->rows[2]->lockColor)->toBe(Color::Green)
        ->and(array_map(fn ($c) => $c->number, $classic->rows[2]->cells))->toBe(range(12, 2));
});

it('returns null for unknown layout ids', function () {
    expect(library()->find('does-not-exist'))->toBeNull();
});

it('exposes a client array shape for the JS engine', function () {
    $client = library()->find('mixed-colors')->toClientArray();

    expect($client['id'])->toBe('mixed-colors')
        ->and($client['rows'])->toHaveCount(4)
        ->and($client['rows'][0]['lock'])->toBe('red')
        ->and($client['rows'][0]['cells'][0])->toBe(['n' => 2, 'c' => 'yellow'])
        ->and($client['rows'][3]['cells'][10])->toBe(['n' => 2, 'c' => 'blue']);
});
