<?php

declare(strict_types=1);

namespace App\Support\Qwixx;

use App\Enums\Qwixx\Color;

final readonly class Cell
{
    public function __construct(
        public int $number,
        public Color $color,
    ) {}
}
