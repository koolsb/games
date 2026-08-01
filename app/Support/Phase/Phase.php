<?php

declare(strict_types=1);

namespace App\Support\Phase;

use App\Enums\Phase\DifficultyBand;

/**
 * An immutable, fully-scored phase. Built by PhaseFactory and never persisted —
 * the library lives in config and is rebuilt in memory each request.
 */
final readonly class Phase
{
    /**
     * @param  list<PhaseComponent>  $components
     */
    public function __construct(
        public array $components,
        public string $label,
        public float $score,
        public DifficultyBand $band,
        public string $signature,
        public ?string $notes = null,
        public string $source = 'enumerated',
    ) {}

    public function totalCards(): int
    {
        return array_sum(array_map(static fn (PhaseComponent $c): int => $c->count, $this->components));
    }

    /**
     * @return list<array{type: string, count: int}>
     */
    public function componentsArray(): array
    {
        return array_map(static fn (PhaseComponent $c): array => $c->toArray(), $this->components);
    }
}
