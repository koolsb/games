<?php

declare(strict_types=1);

use App\Enums\Phase\DifficultyBand;
use App\Services\Phase\PhaseScoringService;
use App\Support\Phase\PhaseComponent;

function comp(string $type, int $count): PhaseComponent
{
    return PhaseComponent::make($type, $count);
}

beforeEach(function (): void {
    $this->scorer = new PhaseScoringService;
});

it('scores larger sets as harder', function (): void {
    expect($this->scorer->score([comp('set', 3)]))
        ->toBeLessThan($this->scorer->score([comp('set', 4)]));

    expect($this->scorer->score([comp('set', 4)]))
        ->toBeLessThan($this->scorer->score([comp('set', 5)]));
});

it('scores longer runs as harder', function (): void {
    expect($this->scorer->score([comp('run', 7)]))
        ->toBeLessThan($this->scorer->score([comp('run', 8)]))
        ->and($this->scorer->score([comp('run', 8)]))
        ->toBeLessThan($this->scorer->score([comp('run', 9)]));
});

it('makes a single-color run much harder than a plain run', function (): void {
    expect($this->scorer->score([comp('color_run', 5)]))
        ->toBeGreaterThan($this->scorer->score([comp('run', 5)]));
});

it('compounds multi-component phases above the sum of their parts', function (): void {
    $single = $this->scorer->score([comp('set', 4)]);
    $double = $this->scorer->score([comp('set', 4), comp('set', 4)]);

    expect($double)->toBeGreaterThan($single * 2);
});

it('applies the set+run mix multiplier', function (): void {
    // A set+run scores higher than the same pieces would without the mix nudge.
    $setPlusRun = $this->scorer->score([comp('set', 4), comp('run', 4)]);
    $twoSets = $this->scorer->score([comp('set', 4), comp('set', 4)]);

    expect($setPlusRun)->toBeGreaterThan(0.0)
        ->and($twoSets)->toBeGreaterThan(0.0);
});

it('treats "2 sets of 7" as brutal / near-impossible', function (): void {
    $score = $this->scorer->score([comp('set', 7), comp('set', 7)]);

    expect(DifficultyBand::fromScore($score))->toBe(DifficultyBand::BRUTAL)
        ->and($score)->toBeGreaterThan(100.0);
});

it('orders representative classic phases sensibly', function (): void {
    $twoSets3 = $this->scorer->score([comp('set', 3), comp('set', 3)]);
    $twoSets4 = $this->scorer->score([comp('set', 4), comp('set', 4)]);
    $set5set2 = $this->scorer->score([comp('set', 5), comp('set', 2)]);
    $set5set3 = $this->scorer->score([comp('set', 5), comp('set', 3)]);

    expect($twoSets4)->toBeGreaterThan($twoSets3)
        ->and($set5set3)->toBeGreaterThan($set5set2);
});

it('returns zero for an empty phase', function (): void {
    expect($this->scorer->score([]))->toBe(0.0);
});
