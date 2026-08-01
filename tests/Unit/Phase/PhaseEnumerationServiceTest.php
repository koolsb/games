<?php

declare(strict_types=1);

use App\Enums\Phase\DifficultyBand;
use App\Services\Phase\PhaseEnumerationService;
use App\Support\Phase\PhaseFactory;

beforeEach(function (): void {
    $this->enumerator = new PhaseEnumerationService;
});

it('respects component-size and total-card bounds', function (): void {
    foreach ($this->enumerator->enumerate(['enabled' => true]) as $components) {
        $total = 0;

        foreach ($components as $component) {
            $total += $component->count;

            if ($component->type->value === 'set') {
                expect($component->count)->toBeLessThanOrEqual(6);
            }
        }

        expect($total)->toBeLessThanOrEqual(10);
    }
});

it('produces unique signatures', function (): void {
    $factory = new PhaseFactory;

    $signatures = array_map(
        fn (array $components): string => $factory->signature($components),
        $this->enumerator->enumerate(['enabled' => true]),
    );

    expect($signatures)->toHaveCount(count(array_unique($signatures)));
});

it('covers every difficulty band', function (): void {
    $factory = new PhaseFactory;
    $bands = [];

    foreach ($this->enumerator->enumerate(['enabled' => true]) as $components) {
        $bands[$factory->make($components)->band->value] = true;
    }

    foreach (DifficultyBand::ordered() as $band) {
        expect($bands)->toHaveKey($band->value);
    }
});

it('returns nothing when disabled', function (): void {
    expect($this->enumerator->enumerate(['enabled' => false]))->toBe([]);
});
