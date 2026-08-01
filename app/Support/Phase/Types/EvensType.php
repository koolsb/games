<?php

declare(strict_types=1);

namespace App\Support\Phase\Types;

class EvensType extends AbstractComponentType
{
    public function label(int $count, int $multiplier): string
    {
        return $this->groupLabel('even cards', $count, $multiplier);
    }

    public function difficulty(int $count): float
    {
        // 48 even-numbered cards exist, but you only ever hold ~10.
        if ($count > 10) {
            return self::IMPOSSIBLE;
        }

        $base = $count ** 1.45 * 1.15;
        $scarcity = 1 + max(0, $count - 6) * 0.25;

        return $base * $scarcity;
    }

    public function enumerationRange(): array
    {
        return [4, 5, 6, 7];
    }
}
