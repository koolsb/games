<?php

declare(strict_types=1);

namespace App\Support\Phase;

final class PhaseLabeler
{
    /**
     * Render an ordered list of components into the canonical Phase 10 label,
     * collapsing runs of identical adjacent components into a multiplier:
     *   [SET(3), SET(3)]          => "2 sets of 3"
     *   [SET(3), RUN(4)]          => "1 set of 3 + 1 run of 4"
     *   [COLOR(7)]                => "7 cards of one color"
     *   [SET(5), SET(2)]          => "1 set of 5 + 1 set of 2"
     *
     * @param  list<PhaseComponent>  $components
     */
    public function label(array $components): string
    {
        $components = array_values($components);
        $parts = [];
        $i = 0;
        $count = count($components);

        while ($i < $count) {
            $component = $components[$i];
            $multiplier = 1;

            while ($i + 1 < $count && $components[$i + 1]->key() === $component->key()) {
                $multiplier++;
                $i++;
            }

            $parts[] = $component->label($multiplier);
            $i++;
        }

        return implode(' + ', $parts);
    }
}
