<?php

declare(strict_types=1);

namespace App\Services\Phase;

use App\Enums\Phase\ComponentType;
use App\Enums\Phase\DifficultyBand;
use App\Support\Phase\PhaseComponent;

/**
 * Computes a difficulty score for a phase from its requirement components.
 *
 * The absolute numbers are arbitrary and tunable — only the relative ordering
 * and resulting bands carry meaning, so tests assert ordering, never magic
 * float values.
 */
final class PhaseScoringService
{
    /** Each extra component compounds difficulty (you satisfy all at once). */
    private const COMBO_PER_EXTRA = 0.18;

    /** A phase mixing a set and a run is a touch harder than either alone. */
    private const SET_AND_RUN_MULTIPLIER = 1.10;

    /**
     * @param  list<PhaseComponent>  $components
     */
    public function score(array $components): float
    {
        if ($components === []) {
            return 0.0;
        }

        $raw = 0.0;
        $hasRun = false;
        $hasSet = false;

        foreach ($components as $component) {
            $raw += $component->difficulty();

            if ($component->type->strategy()->isRunLike()) {
                $hasRun = true;
            }

            if ($component->type === ComponentType::SET) {
                $hasSet = true;
            }
        }

        $k = count($components);
        $combo = $raw * (1 + self::COMBO_PER_EXTRA * ($k - 1));
        $mixed = $combo * (($hasRun && $hasSet) ? self::SET_AND_RUN_MULTIPLIER : 1.0);

        return round($mixed, 2);
    }

    /**
     * @param  list<PhaseComponent>  $components
     */
    public function band(array $components): DifficultyBand
    {
        return DifficultyBand::fromScore($this->score($components));
    }
}
