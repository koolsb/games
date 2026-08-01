<?php

declare(strict_types=1);

namespace App\Services\Phase;

use App\Enums\Phase\ComponentType;
use App\Support\Phase\PhaseComponent;

/**
 * Programmatically enumerates a large, sane library of phase variants so the
 * generator always has a well-populated pool in every difficulty band.
 *
 * Returns raw component lists; PhaseLibrary scores, labels and dedupes them.
 */
final class PhaseEnumerationService
{
    /**
     * @param  array{enabled?: bool, max_total_cards?: int, types?: list<string>, two_component?: bool, three_component?: bool}  $config
     * @return list<list<PhaseComponent>>
     */
    public function enumerate(array $config = []): array
    {
        if (($config['enabled'] ?? true) === false) {
            return [];
        }

        $max = $config['max_total_cards'] ?? 10;
        $types = $config['types'] ?? array_map(fn (ComponentType $t): string => $t->value, ComponentType::cases());
        $types = array_map(static fn (string $t): ComponentType => ComponentType::from($t), $types);

        $phases = [];

        foreach ($this->singles($types) as $phase) {
            $phases[] = $phase;
        }

        if (($config['two_component'] ?? true) === true) {
            foreach ($this->pairs($max) as $phase) {
                $phases[] = $phase;
            }
        }

        if (($config['three_component'] ?? true) === true) {
            foreach ($this->triples($max) as $phase) {
                $phases[] = $phase;
            }
        }

        return $phases;
    }

    /**
     * @param  list<ComponentType>  $types
     * @return list<list<PhaseComponent>>
     */
    private function singles(array $types): array
    {
        $phases = [];

        foreach ($types as $type) {
            foreach ($type->strategy()->enumerationRange() as $count) {
                $phases[] = [new PhaseComponent($type, $count)];
            }
        }

        return $phases;
    }

    /**
     * Curated two-component shapes, bounded by total cards held (10).
     *
     * @return list<list<PhaseComponent>>
     */
    private function pairs(int $max): array
    {
        $shapes = [
            // [typeA, rangeA, typeB, rangeB, sameTypeOrdered]
            [ComponentType::SET, [2, 3, 4, 5, 6], ComponentType::SET, [2, 3, 4, 5, 6], true],
            [ComponentType::SET, [2, 3, 4, 5], ComponentType::RUN, [4, 5, 6, 7], false],
            [ComponentType::SET, [2, 3, 4], ComponentType::COLOR, [4, 5, 6], false],
            [ComponentType::RUN, [4, 5], ComponentType::COLOR, [4, 5], false],
            [ComponentType::EVENS, [4, 5], ComponentType::ODDS, [4, 5], false],
            [ComponentType::SET, [2, 3], ComponentType::COLOR_RUN, [4, 5], false],
        ];

        $phases = [];

        foreach ($shapes as [$typeA, $rangeA, $typeB, $rangeB, $sameOrdered]) {
            foreach ($rangeA as $a) {
                foreach ($rangeB as $b) {
                    // For same-type pairs, only keep a <= b to avoid mirror dupes.
                    if ($sameOrdered && $a > $b) {
                        continue;
                    }

                    if ($a + $b > $max) {
                        continue;
                    }

                    $phases[] = [
                        new PhaseComponent($typeA, $a),
                        new PhaseComponent($typeB, $b),
                    ];
                }
            }
        }

        return $phases;
    }

    /**
     * A small whitelist of three-component shapes.
     *
     * @return list<list<PhaseComponent>>
     */
    private function triples(int $max): array
    {
        $phases = [];

        // Three sets, non-decreasing sizes.
        foreach ([2, 3, 4] as $a) {
            foreach ([2, 3, 4] as $b) {
                foreach ([2, 3, 4] as $c) {
                    if ($a > $b || $b > $c) {
                        continue;
                    }

                    if ($a + $b + $c > $max) {
                        continue;
                    }

                    $phases[] = [
                        new PhaseComponent(ComponentType::SET, $a),
                        new PhaseComponent(ComponentType::SET, $b),
                        new PhaseComponent(ComponentType::SET, $c),
                    ];
                }
            }
        }

        // Two sets + a run.
        foreach ([2, 3] as $a) {
            foreach ([2, 3] as $b) {
                foreach ([4, 5] as $run) {
                    if ($a > $b || $a + $b + $run > $max) {
                        continue;
                    }

                    $phases[] = [
                        new PhaseComponent(ComponentType::SET, $a),
                        new PhaseComponent(ComponentType::SET, $b),
                        new PhaseComponent(ComponentType::RUN, $run),
                    ];
                }
            }
        }

        return $phases;
    }
}
