<?php

declare(strict_types=1);

namespace App\Support\Phase;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Thin wrapper around PHP's Randomizer so every random decision in generation
 * flows through one seedable source. A non-null seed gives a fully
 * reproducible sequence (used for shareable games and deterministic tests).
 */
final class RandomEngine
{
    private readonly Randomizer $randomizer;

    public function __construct(public readonly ?int $seed = null)
    {
        $this->randomizer = $seed === null
            ? new Randomizer
            : new Randomizer(new Mt19937($seed));
    }

    public function int(int $min, int $max): int
    {
        return $this->randomizer->getInt($min, $max);
    }

    public function float(float $min = 0.0, float $max = 1.0): float
    {
        return $this->randomizer->getFloat($min, $max);
    }
}
