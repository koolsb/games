<?php

declare(strict_types=1);

namespace App\Support\Qwixx;

use App\Enums\Qwixx\Color;

final readonly class Row
{
    /**
     * @param  list<Cell>  $cells  exactly 11, in left-to-right play order
     */
    public function __construct(
        public int $index,
        public array $cells,
        public Color $lockColor,
    ) {}

    /**
     * True when every cell shares one color (classic / mixed-numbers rows).
     */
    public function isSolid(): bool
    {
        foreach ($this->cells as $cell) {
            if ($cell->color !== $this->lockColor) {
                return false;
            }
        }

        return true;
    }
}
