<?php

declare(strict_types=1);

use App\Services\Phase\PhaseLibrary;
use App\Support\Phase\Phase;

it('builds only the configured phases when enumeration is off', function (): void {
    $library = new PhaseLibrary([
        'classics' => [[['set', 3], ['set', 3]], [['run', 7]]],
        'enumeration' => ['enabled' => false],
    ]);

    expect($library->all())->toHaveCount(2);
});

it('lets config-defined phases win over enumerated duplicates', function (): void {
    $library = new PhaseLibrary([
        'classics' => [[['run', 7]]],
        'enumeration' => ['enabled' => true],
    ]);

    $run7 = $library->all()->first(fn (Phase $p): bool => $p->label === '1 run of 7');

    expect($run7)->not->toBeNull()
        ->and($run7->source)->toBe('classic');
});

it('parses the components-with-notes form', function (): void {
    $library = new PhaseLibrary([
        'custom' => [['components' => [['color_run', 5]], 'notes' => 'Rainbow nightmare']],
        'enumeration' => ['enabled' => false],
    ]);

    $phase = $library->all()->first();

    expect($phase->notes)->toBe('Rainbow nightmare')
        ->and($phase->source)->toBe('custom')
        ->and($phase->label)->toBe('1 run of 5 of one color');
});

it('reports counts for all four bands', function (): void {
    $library = new PhaseLibrary(['enumeration' => ['enabled' => true]]);

    expect(array_keys($library->bandCounts()))->toBe(['easy', 'medium', 'hard', 'brutal']);
});

it('finds a phase by signature', function (): void {
    $library = new PhaseLibrary([
        'classics' => [[['run', 7]]],
        'enumeration' => ['enabled' => false],
    ]);

    $phase = $library->all()->first();

    expect($library->find($phase->signature))->not->toBeNull()
        ->and($library->find('nope'))->toBeNull();
});
