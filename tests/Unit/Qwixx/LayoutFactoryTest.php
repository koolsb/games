<?php

declare(strict_types=1);

use App\Enums\Qwixx\Color;
use App\Support\Qwixx\LayoutFactory;

function solidRows(): array
{
    return [
        ['color' => 'red', 'numbers' => range(2, 12)],
        ['color' => 'yellow', 'numbers' => range(2, 12)],
        ['color' => 'green', 'numbers' => range(12, 2)],
        ['color' => 'blue', 'numbers' => range(12, 2)],
    ];
}

it('normalizes solid rows into cells sharing the row color', function () {
    $layout = (new LayoutFactory)->make('classic', ['name' => 'Classic', 'rows' => solidRows()]);

    expect($layout->rows)->toHaveCount(4)
        ->and($layout->rows[0]->lockColor)->toBe(Color::Red)
        ->and($layout->rows[0]->cells)->toHaveCount(11)
        ->and($layout->rows[0]->cells[0]->number)->toBe(2)
        ->and($layout->rows[0]->cells[10]->number)->toBe(12)
        ->and($layout->rows[2]->cells[0]->number)->toBe(12)
        ->and($layout->rows[0]->isSolid())->toBeTrue();
});

it('normalizes per-cell rows with their own colors', function () {
    $rows = [
        ['lock' => 'red', 'cells' => [
            [2, 'yellow'], [3, 'yellow'], [4, 'yellow'], [5, 'blue'], [6, 'blue'], [7, 'blue'],
            [8, 'green'], [9, 'green'], [10, 'green'], [11, 'red'], [12, 'red'],
        ]],
        ['lock' => 'yellow', 'cells' => [
            [2, 'red'], [3, 'red'], [4, 'green'], [5, 'green'], [6, 'green'], [7, 'green'],
            [8, 'blue'], [9, 'blue'], [10, 'yellow'], [11, 'yellow'], [12, 'yellow'],
        ]],
        ['lock' => 'green', 'cells' => [
            [12, 'blue'], [11, 'blue'], [10, 'blue'], [9, 'yellow'], [8, 'yellow'], [7, 'yellow'],
            [6, 'red'], [5, 'red'], [4, 'red'], [3, 'green'], [2, 'green'],
        ]],
        ['lock' => 'blue', 'cells' => [
            [12, 'green'], [11, 'green'], [10, 'red'], [9, 'red'], [8, 'red'], [7, 'red'],
            [6, 'yellow'], [5, 'yellow'], [4, 'blue'], [3, 'blue'], [2, 'blue'],
        ]],
    ];

    $layout = (new LayoutFactory)->make('mixed', ['rows' => $rows]);

    expect($layout->rows[0]->cells[0]->color)->toBe(Color::Yellow)
        ->and($layout->rows[0]->lockColor)->toBe(Color::Red)
        ->and($layout->rows[0]->isSolid())->toBeFalse();
});

it('rejects layouts without exactly four rows', function () {
    (new LayoutFactory)->make('bad', ['rows' => array_slice(solidRows(), 0, 3)]);
})->throws(InvalidArgumentException::class, 'exactly 4 rows');

it('rejects rows that are not a permutation of 2-12', function () {
    $rows = solidRows();
    $rows[0]['numbers'][10] = 2; // duplicate 2, missing 12

    (new LayoutFactory)->make('bad', ['rows' => $rows]);
})->throws(InvalidArgumentException::class, '2-12 exactly once');

it('rejects unknown colors', function () {
    $rows = solidRows();
    $rows[0]['color'] = 'purple';

    (new LayoutFactory)->make('bad', ['rows' => $rows]);
})->throws(InvalidArgumentException::class, 'unknown color');

it('rejects sheets where a color does not own exactly 11 cells', function () {
    $rows = solidRows();
    $rows[1]['color'] = 'red'; // 22 red cells, 0 yellow

    (new LayoutFactory)->make('bad', ['rows' => $rows]);
})->throws(InvalidArgumentException::class, 'red cells');

it('rejects rows missing both numbers and cells', function () {
    $rows = solidRows();
    unset($rows[0]['numbers']);

    (new LayoutFactory)->make('bad', ['rows' => $rows]);
})->throws(InvalidArgumentException::class, "'numbers' or 'cells'");
