<?php

declare(strict_types=1);

use App\Support\Phase\RandomEngine;
use App\Support\Phase\WeightedPicker;

beforeEach(function (): void {
    $this->picker = new WeightedPicker;
});

it('is deterministic for a given seed', function (): void {
    $items = ['a', 'b', 'c', 'd'];
    $weights = [1.0, 2.0, 3.0, 4.0];

    $first = $this->picker->pick($items, $weights, new RandomEngine(42));
    $second = $this->picker->pick($items, $weights, new RandomEngine(42));

    expect($first)->toBe($second);
});

it('favors higher-weighted items over many draws', function (): void {
    $items = ['rare', 'common'];
    $weights = [1.0, 9.0];

    $rng = new RandomEngine(7);
    $counts = ['rare' => 0, 'common' => 0];

    for ($i = 0; $i < 1000; $i++) {
        $counts[$this->picker->pick($items, $weights, $rng)]++;
    }

    expect($counts['common'])->toBeGreaterThan($counts['rare'] * 3);
});

it('throws when picking from an empty set', function (): void {
    $this->picker->pick([], [], new RandomEngine(1));
})->throws(InvalidArgumentException::class);
