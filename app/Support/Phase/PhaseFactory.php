<?php

declare(strict_types=1);

namespace App\Support\Phase;

use App\Enums\Phase\DifficultyBand;
use App\Services\Phase\PhaseScoringService;

/**
 * Builds fully-scored Phase value objects from raw component lists.
 */
final class PhaseFactory
{
    public function __construct(
        private readonly PhaseScoringService $scorer = new PhaseScoringService,
        private readonly PhaseLabeler $labeler = new PhaseLabeler,
    ) {}

    /**
     * @param  list<PhaseComponent>  $components
     */
    public function make(array $components, ?string $notes = null, string $source = 'enumerated'): Phase
    {
        $components = array_values($components);
        $score = $this->scorer->score($components);

        return new Phase(
            components: $components,
            label: $this->labeler->label($components),
            score: $score,
            band: DifficultyBand::fromScore($score),
            signature: $this->signature($components),
            notes: $notes,
            source: $source,
        );
    }

    /**
     * Canonical, order-independent fingerprint of a component list — used to
     * dedupe phases regardless of the order their components were declared in.
     *
     * @param  list<PhaseComponent>  $components
     */
    public function signature(array $components): string
    {
        $keys = array_map(static fn (PhaseComponent $c): string => $c->key(), $components);
        sort($keys);

        return substr(sha1(implode('|', $keys)), 0, 16);
    }
}
