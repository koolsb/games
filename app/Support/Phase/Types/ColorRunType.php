<?php

declare(strict_types=1);

namespace App\Support\Phase\Types;

class ColorRunType extends AbstractComponentType
{
    public function label(int $count, int $multiplier): string
    {
        $noun = $multiplier === 1 ? 'run' : 'runs';

        return "{$multiplier} {$noun} of {$count} of one color";
    }

    public function colorConstrained(): bool
    {
        return true;
    }

    public function isRunLike(): bool
    {
        return true;
    }

    public function difficulty(int $count): float
    {
        if ($count > 12) {
            return self::IMPOSSIBLE;
        }

        // A run that must also be a single colour is far harder than a plain run.
        $runBase = $count ** 1.35 * 1.05;
        $base = $runBase * 2.4;
        $scarcity = 1 + max(0, $count - 5) * 0.4;

        return $base * $scarcity;
    }

    public function enumerationRange(): array
    {
        return [4, 5, 6, 7];
    }
}
