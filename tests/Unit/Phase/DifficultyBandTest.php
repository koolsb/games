<?php

declare(strict_types=1);

use App\Enums\Phase\DifficultyBand;

it('maps scores to bands at the boundaries', function (): void {
    expect(DifficultyBand::fromScore(9.99))->toBe(DifficultyBand::EASY)
        ->and(DifficultyBand::fromScore(10.0))->toBe(DifficultyBand::MEDIUM)
        ->and(DifficultyBand::fromScore(21.99))->toBe(DifficultyBand::MEDIUM)
        ->and(DifficultyBand::fromScore(22.0))->toBe(DifficultyBand::HARD)
        ->and(DifficultyBand::fromScore(44.99))->toBe(DifficultyBand::HARD)
        ->and(DifficultyBand::fromScore(45.0))->toBe(DifficultyBand::BRUTAL);
});

it('orders bands easy to brutal', function (): void {
    expect(DifficultyBand::ordered())->toBe([
        DifficultyBand::EASY,
        DifficultyBand::MEDIUM,
        DifficultyBand::HARD,
        DifficultyBand::BRUTAL,
    ]);

    expect(DifficultyBand::EASY->index())->toBe(0)
        ->and(DifficultyBand::BRUTAL->index())->toBe(3);
});

it('exposes a sane score range per band', function (): void {
    [$min, $max] = DifficultyBand::MEDIUM->range();

    expect($min)->toBe(10.0)->and($max)->toBe(22.0);
});
