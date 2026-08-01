<?php

declare(strict_types=1);

namespace App\Enums\Qwixx;

/**
 * The four Qwixx dice colors. Variant sheets may scatter these across
 * cells, but scoring always tallies marks per color.
 */
enum Color: string
{
    case Red = 'red';
    case Yellow = 'yellow';
    case Green = 'green';
    case Blue = 'blue';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
