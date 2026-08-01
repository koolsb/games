<?php

declare(strict_types=1);

namespace App\Support\Phase\Types;

class ColorType extends AbstractComponentType
{
    public function label(int $count, int $multiplier): string
    {
        return $this->groupLabel('cards of one color', $count, $multiplier);
    }

    public function colorConstrained(): bool
    {
        return true;
    }

    public function difficulty(int $count): float
    {
        if ($count > 11) {
            return self::IMPOSSIBLE;
        }

        $base = $count ** 1.5 * 1.25;
        $scarcity = 1 + max(0, $count - 6) * 0.3;

        return $base * $scarcity;
    }

    public function enumerationRange(): array
    {
        return [4, 5, 6, 7, 8];
    }
}
