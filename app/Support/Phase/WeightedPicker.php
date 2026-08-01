<?php

declare(strict_types=1);

namespace App\Support\Phase;

use InvalidArgumentException;

final class WeightedPicker
{
    /**
     * Pick one item using cumulative weights and a single random draw.
     *
     * @template T
     *
     * @param  list<T>  $items
     * @param  list<float>  $weights  parallel to $items
     * @return T
     */
    public function pick(array $items, array $weights, RandomEngine $rng): mixed
    {
        $items = array_values($items);
        $weights = array_values($weights);

        if ($items === []) {
            throw new InvalidArgumentException('Cannot pick from an empty set.');
        }

        $total = array_sum($weights);

        // Degenerate weights → uniform fallback.
        if ($total <= 0.0) {
            return $items[$rng->int(0, count($items) - 1)];
        }

        $target = $rng->float(0.0, $total);
        $cumulative = 0.0;

        foreach ($items as $index => $item) {
            $cumulative += $weights[$index];

            if ($target <= $cumulative) {
                return $item;
            }
        }

        return $items[array_key_last($items)];
    }
}
