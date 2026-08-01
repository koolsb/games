<?php

declare(strict_types=1);

namespace App\Services\Phase;

use App\Enums\Phase\DifficultyBand;
use App\Enums\Phase\GenerationMode;
use App\Support\Phase\GeneratedGame;
use App\Support\Phase\GenerationOptions;
use App\Support\Phase\Phase;
use App\Support\Phase\RandomEngine;
use App\Support\Phase\WeightedPicker;
use Illuminate\Support\Collection;

/**
 * Builds a game (ordered list of phases) from a pool of scored phases.
 *
 * Pure and pool-driven: callers pass the candidate pool, so the service is
 * unit-testable without the config-backed library.
 */
final class PhaseGeneratorService
{
    public function __construct(
        private readonly WeightedPicker $picker = new WeightedPicker,
    ) {}

    /**
     * @param  Collection<int, Phase>  $pool
     */
    public function generateGame(Collection $pool, GenerationOptions $options): GeneratedGame
    {
        $pool = $this->applyBandRange($pool, $options)->values();
        $seed = $options->seed ?? random_int(1, PHP_INT_MAX);
        $rng = new RandomEngine($seed);

        $targets = $this->slotTargets($options);
        $used = [];
        $phases = [];
        $widenedSlots = [];

        foreach ($targets as $index => $target) {
            $result = $this->pickForSlot($pool, $target, $used, $options->allowDuplicates, $rng);

            $phases[] = $result['phase'];
            $used[] = $result['phase']->signature;

            if ($result['widened']) {
                $widenedSlots[] = $index;
            }
        }

        return new GeneratedGame($phases, $seed, $options->mode, $widenedSlots);
    }

    /**
     * Replace a single slot, avoiding the phases already in the game.
     *
     * @param  Collection<int, Phase>  $pool
     */
    public function regenerateSlot(Collection $pool, GeneratedGame $game, int $slotIndex, GenerationOptions $options): GeneratedGame
    {
        $pool = $this->applyBandRange($pool, $options)->values();
        $rng = new RandomEngine($options->seed ?? random_int(1, PHP_INT_MAX));

        $target = $this->targetForSlot($slotIndex, $options);

        // Exclude every current slot (including this one) so the pick changes.
        $used = array_map(static fn (Phase $p): string => $p->signature, $game->phases);

        $result = $this->pickForSlot($pool, $target, $used, $options->allowDuplicates, $rng);

        $phases = $game->phases;
        $phases[$slotIndex] = $result['phase'];

        $widened = array_values(array_filter(
            $game->widenedSlots,
            static fn (int $slot): bool => $slot !== $slotIndex,
        ));

        if ($result['widened']) {
            $widened[] = $slotIndex;
        }

        return new GeneratedGame(array_values($phases), $game->seed, $game->mode, $widened);
    }

    /**
     * Generate a single phase, optionally constrained to a band.
     *
     * @param  Collection<int, Phase>  $pool
     */
    public function generateOne(Collection $pool, GenerationOptions $options): Phase
    {
        $pool = $this->applyBandRange($pool, $options)->values();
        $rng = new RandomEngine($options->seed ?? random_int(1, PHP_INT_MAX));

        $candidates = $options->band !== null
            ? $pool->filter(fn (Phase $p): bool => $p->band === $options->band)->values()
            : $pool;

        if ($candidates->isEmpty()) {
            $candidates = $pool;
        }

        $items = $candidates->all();

        return $this->picker->pick($items, array_fill(0, count($items), 1.0), $rng);
    }

    /**
     * @return list<array{band: DifficultyBand, ideal: float}>
     */
    private function slotTargets(GenerationOptions $options): array
    {
        $targets = [];

        for ($i = 0; $i < $options->count; $i++) {
            $targets[] = $this->targetForSlot($i, $options);
        }

        return $targets;
    }

    /**
     * @return array{band: DifficultyBand, ideal: float}
     */
    private function targetForSlot(int $index, GenerationOptions $options): array
    {
        return match ($options->mode) {
            GenerationMode::RAMP => $this->rampTarget($index, $options->count, $options->minBand, $options->maxBand),
            GenerationMode::FLAT => $this->bandTarget($options->band ?? DifficultyBand::MEDIUM),
            GenerationMode::MANUAL => $this->bandTarget($this->manualBand($index, $options)),
        };
    }

    /**
     * @return array{band: DifficultyBand, ideal: float}
     */
    private function rampTarget(int $index, int $count, ?DifficultyBand $minBand, ?DifficultyBand $maxBand): array
    {
        $position = $count <= 1 ? 0.0 : $index / ($count - 1);

        $band = match (true) {
            $position < 0.2 => DifficultyBand::EASY,
            $position < 0.5 => DifficultyBand::MEDIUM,
            $position < 0.8 => DifficultyBand::HARD,
            default => DifficultyBand::BRUTAL,
        };

        // Clamp to the user's selected range so slots never target a band outside it.
        if ($minBand !== null && $band->index() < $minBand->index()) {
            $band = $minBand;
        }
        if ($maxBand !== null && $band->index() > $maxBand->index()) {
            $band = $maxBand;
        }

        return ['band' => $band, 'ideal' => $this->lerp(2.0, 55.0, $position)];
    }

    /**
     * @return array{band: DifficultyBand, ideal: float}
     */
    private function bandTarget(DifficultyBand $band): array
    {
        [$min, $max] = $band->range();

        return ['band' => $band, 'ideal' => ($min + $max) / 2.0];
    }

    private function manualBand(int $index, GenerationOptions $options): DifficultyBand
    {
        $bands = $options->slotBands ?? [];

        if ($bands === []) {
            return DifficultyBand::MEDIUM;
        }

        return $bands[$index] ?? $bands[array_key_last($bands)];
    }

    /**
     * @param  Collection<int, Phase>  $pool
     * @param  list<string>  $usedSignatures
     * @param  array{band: DifficultyBand, ideal: float}  $target
     * @return array{phase: Phase, widened: bool}
     */
    private function pickForSlot(Collection $pool, array $target, array $usedSignatures, bool $allowDuplicates, RandomEngine $rng): array
    {
        $band = $target['band'];
        $ideal = $target['ideal'];

        $available = $this->availablePool($pool, $usedSignatures, $allowDuplicates);

        $candidates = $available->filter(fn (Phase $p): bool => $p->band === $band)->values();
        $widened = false;

        if ($candidates->isEmpty()) {
            $widened = true;
            $candidates = $this->widen($available, $band);
        }

        // Pool exhausted by the no-duplicate rule — fall back to the full pool.
        if ($candidates->isEmpty()) {
            $widened = true;
            $candidates = $pool->filter(fn (Phase $p): bool => $p->band === $band)->values();

            if ($candidates->isEmpty()) {
                $candidates = $pool->values();
            }
        }

        $items = $candidates->all();
        $weights = array_map(
            static fn (Phase $p): float => 1.0 / (1.0 + abs($p->score - $ideal)),
            $items,
        );

        return ['phase' => $this->picker->pick($items, $weights, $rng), 'widened' => $widened];
    }

    /**
     * @param  Collection<int, Phase>  $pool
     * @param  list<string>  $usedSignatures
     * @return Collection<int, Phase>
     */
    private function availablePool(Collection $pool, array $usedSignatures, bool $allowDuplicates): Collection
    {
        if ($allowDuplicates) {
            return $pool;
        }

        $used = array_flip($usedSignatures);

        return $pool->reject(fn (Phase $p): bool => isset($used[$p->signature]))->values();
    }

    /**
     * Find the nearest non-empty band to the target.
     *
     * @param  Collection<int, Phase>  $available
     * @return Collection<int, Phase>
     */
    private function widen(Collection $available, DifficultyBand $band): Collection
    {
        $bands = DifficultyBand::ordered();
        usort(
            $bands,
            static fn (DifficultyBand $a, DifficultyBand $b): int => abs($a->index() - $band->index()) <=> abs($b->index() - $band->index()),
        );

        foreach ($bands as $candidateBand) {
            if ($candidateBand === $band) {
                continue;
            }

            $candidates = $available->filter(fn (Phase $p): bool => $p->band === $candidateBand)->values();

            if ($candidates->isNotEmpty()) {
                return $candidates;
            }
        }

        return $available->values();
    }

    /**
     * Restrict the pool to phases whose band falls within [minBand, maxBand].
     * When neither bound is set the pool is returned unchanged.
     *
     * @param  Collection<int, Phase>  $pool
     * @return Collection<int, Phase>
     */
    private function applyBandRange(Collection $pool, GenerationOptions $options): Collection
    {
        if ($options->minBand === null && $options->maxBand === null) {
            return $pool;
        }

        $min = $options->minBand?->index() ?? 0;
        $max = $options->maxBand?->index() ?? 3;

        return $pool->filter(fn (Phase $p): bool => $p->band->index() >= $min && $p->band->index() <= $max);
    }

    private function lerp(float $from, float $to, float $t): float
    {
        return $from + ($to - $from) * $t;
    }
}
