<?php

declare(strict_types=1);

namespace App\Support\Phase\Types;

class SetType extends AbstractComponentType
{
    public function label(int $count, int $multiplier): string
    {
        return $this->nounLabel('set', $count, $multiplier);
    }

    public function difficulty(int $count): float
    {
        // Only 8 copies of any one number exist (4 colours x 2). A set larger
        // than 8 is impossible without leaning entirely on wilds.
        if ($count > 8) {
            return self::IMPOSSIBLE;
        }

        $base = $count ** 1.6;
        $scarcity = 1 + max(0, $count - 4) * 0.6;

        return $base * $scarcity;
    }

    public function enumerationRange(): array
    {
        return [2, 3, 4, 5, 6];
    }
}
