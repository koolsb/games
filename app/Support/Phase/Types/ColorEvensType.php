<?php

declare(strict_types=1);

namespace App\Support\Phase\Types;

class ColorEvensType extends AbstractComponentType
{
    public function label(int $count, int $multiplier): string
    {
        return $this->groupLabel('even cards of one color', $count, $multiplier);
    }

    public function colorConstrained(): bool
    {
        return true;
    }

    public function difficulty(int $count): float
    {
        // Per colour there are only 12 even cards (6 even numbers x 2).
        if ($count > 12) {
            return self::IMPOSSIBLE;
        }

        $evensBase = $count ** 1.45 * 1.15;
        $base = $evensBase * 2.2;
        $scarcity = 1 + max(0, $count - 4) * 0.5;

        return $base * $scarcity;
    }

    public function enumerationRange(): array
    {
        return [3, 4, 5, 6];
    }
}
