<?php

declare(strict_types=1);

use App\Enums\Phase\DifficultyBand;
use App\Enums\Phase\GenerationMode;
use App\Services\Phase\PhaseEnumerationService;
use App\Services\Phase\PhaseGeneratorService;
use App\Support\Phase\GenerationOptions;
use App\Support\Phase\Phase;
use App\Support\Phase\PhaseFactory;
use Illuminate\Support\Collection;

/**
 * @return Collection<int, Phase>
 */
function generatorPool(): Collection
{
    $factory = new PhaseFactory;
    $phases = [];

    foreach ((new PhaseEnumerationService)->enumerate(['enabled' => true]) as $components) {
        $phase = $factory->make($components);
        $phases[$phase->signature] = $phase;
    }

    return collect(array_values($phases));
}

beforeEach(function (): void {
    $this->generator = new PhaseGeneratorService;
    $this->pool = generatorPool();
});

it('is deterministic for a fixed seed', function (): void {
    $options = new GenerationOptions(count: 10, mode: GenerationMode::RAMP, seed: 12345);

    $a = $this->generator->generateGame($this->pool, $options);
    $b = $this->generator->generateGame($this->pool, $options);

    expect($a->signatures())->toBe($b->signatures());
});

it('produces no duplicate phases within a game', function (): void {
    $game = $this->generator->generateGame($this->pool, new GenerationOptions(count: 10, seed: 1));

    expect($game->signatures())->toHaveCount(10)
        ->and(array_unique($game->signatures()))->toHaveCount(10);
});

it('ramps difficulty from first slot to last', function (): void {
    $game = $this->generator->generateGame($this->pool, new GenerationOptions(count: 10, mode: GenerationMode::RAMP, seed: 99));

    expect($game->phases[9]->band->index())->toBeGreaterThanOrEqual($game->phases[0]->band->index());

    $firstHalf = array_sum(array_map(fn (Phase $p): float => $p->score, array_slice($game->phases, 0, 5))) / 5;
    $secondHalf = array_sum(array_map(fn (Phase $p): float => $p->score, array_slice($game->phases, 5, 5))) / 5;

    expect($secondHalf)->toBeGreaterThan($firstHalf);
});

it('keeps every phase in the chosen band in flat mode', function (): void {
    $game = $this->generator->generateGame(
        $this->pool,
        new GenerationOptions(count: 8, mode: GenerationMode::FLAT, band: DifficultyBand::MEDIUM, seed: 5),
    );

    foreach ($game->phases as $phase) {
        expect($phase->band)->toBe(DifficultyBand::MEDIUM);
    }

    expect($game->widenedSlots)->toBe([]);
});

it('honors per-slot bands in manual mode', function (): void {
    $slotBands = [DifficultyBand::EASY, DifficultyBand::MEDIUM, DifficultyBand::HARD, DifficultyBand::BRUTAL];

    $game = $this->generator->generateGame(
        $this->pool,
        new GenerationOptions(count: 4, mode: GenerationMode::MANUAL, slotBands: $slotBands, seed: 3),
    );

    foreach ($slotBands as $index => $band) {
        expect($game->phases[$index]->band)->toBe($band);
    }
});

it('respects the count parameter', function (): void {
    expect($this->generator->generateGame($this->pool, new GenerationOptions(count: 1, seed: 1))->count())->toBe(1)
        ->and($this->generator->generateGame($this->pool, new GenerationOptions(seed: 1))->count())->toBe(10);
});

it('regenerates only the targeted slot', function (): void {
    $options = new GenerationOptions(count: 10, mode: GenerationMode::RAMP, seed: 2024);
    $game = $this->generator->generateGame($this->pool, $options);
    $before = $game->signatures();

    $updated = $this->generator->regenerateSlot($this->pool, $game, 3, $options->withSeed(777));
    $after = $updated->signatures();

    expect($after[3])->not->toBe($before[3]);

    foreach ([0, 1, 2, 4, 5, 6, 7, 8, 9] as $index) {
        expect($after[$index])->toBe($before[$index]);
    }
});
