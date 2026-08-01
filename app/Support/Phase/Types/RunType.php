<?php

declare(strict_types=1);

namespace App\Support\Phase\Types;

class RunType extends AbstractComponentType
{
    public function label(int $count, int $multiplier): string
    {
        return $this->nounLabel('run', $count, $multiplier);
    }

    public function isRunLike(): bool
    {
        return true;
    }

    public function difficulty(int $count): float
    {
        // Numbers only run 1-12, so a run longer than 12 cannot exist.
        if ($count > 12) {
            return self::IMPOSSIBLE;
        }

        $base = $count ** 1.35 * 1.05;
        $scarcity = 1 + max(0, $count - 6) * 0.15;

        return $base * $scarcity;
    }

    public function enumerationRange(): array
    {
        return [4, 5, 6, 7, 8, 9];
    }
}
