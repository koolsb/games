<?php

declare(strict_types=1);

namespace App\Support\Phase\Types;

use App\Contracts\Phase\ComponentTypeContract;

abstract class AbstractComponentType implements ComponentTypeContract
{
    /** Score returned for combinations that cannot exist in a real deck. */
    protected const IMPOSSIBLE = 999.0;

    public function colorConstrained(): bool
    {
        return false;
    }

    public function isRunLike(): bool
    {
        return false;
    }

    /**
     * Noun-led label that always carries the leading count:
     * "1 set of 3", "2 sets of 3", "1 run of 7".
     */
    protected function nounLabel(string $singular, int $count, int $multiplier): string
    {
        $noun = $multiplier === 1 ? $singular : $singular.'s';

        return "{$multiplier} {$noun} of {$count}";
    }

    /**
     * Count-led label (no leading "1" for a single group):
     * "7 cards of one color", "2 groups of 4 even cards".
     */
    protected function groupLabel(string $tail, int $count, int $multiplier): string
    {
        if ($multiplier === 1) {
            return "{$count} {$tail}";
        }

        return "{$multiplier} groups of {$count} {$tail}";
    }
}
