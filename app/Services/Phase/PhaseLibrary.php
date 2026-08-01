<?php

declare(strict_types=1);

namespace App\Services\Phase;

use App\Enums\Phase\DifficultyBand;
use App\Support\Phase\Phase;
use App\Support\Phase\PhaseComponent;
use App\Support\Phase\PhaseFactory;
use Illuminate\Support\Collection;

/**
 * Builds the full, in-memory phase library from config/phases.php plus the
 * programmatically enumerated variants. Config-defined phases (classics,
 * custom) win over enumerated ones with the same signature.
 */
final class PhaseLibrary
{
    /** @var Collection<int, Phase>|null */
    private ?Collection $cache = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config = [],
        private readonly PhaseFactory $factory = new PhaseFactory,
        private readonly PhaseEnumerationService $enumerator = new PhaseEnumerationService,
    ) {}

    /**
     * Every phase in the library, deduped by signature.
     *
     * @return Collection<int, Phase>
     */
    public function all(): Collection
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $phases = [];

        $this->collect($phases, $this->config['classics'] ?? [], 'classic');
        $this->collect($phases, $this->config['custom'] ?? [], 'custom');

        foreach ($this->enumerator->enumerate($this->config['enumeration'] ?? []) as $components) {
            $phase = $this->factory->make($components, source: 'enumerated');
            $phases[$phase->signature] ??= $phase;
        }

        return $this->cache = collect(array_values($phases));
    }

    /**
     * @return Collection<int, Phase>
     */
    public function inBand(DifficultyBand $band): Collection
    {
        return $this->all()->filter(fn (Phase $p): bool => $p->band === $band)->values();
    }

    public function find(string $signature): ?Phase
    {
        return $this->all()->first(fn (Phase $p): bool => $p->signature === $signature);
    }

    /**
     * Count of phases per band, ordered easy→brutal (for the UI).
     *
     * @return array<string, int>
     */
    public function bandCounts(): array
    {
        $counts = [];

        foreach (DifficultyBand::ordered() as $band) {
            $counts[$band->value] = $this->inBand($band)->count();
        }

        return $counts;
    }

    /**
     * @param  array<string, Phase>  $phases  keyed by signature (mutated)
     * @param  array<int, mixed>  $definitions
     */
    private function collect(array &$phases, array $definitions, string $source): void
    {
        foreach ($definitions as $definition) {
            [$components, $notes] = $this->parse($definition);

            if ($components === []) {
                continue;
            }

            $phase = $this->factory->make($components, $notes, $source);
            $phases[$phase->signature] = $phase;
        }
    }

    /**
     * Normalize a config entry into components + notes. Accepts either a bare
     * list of [type, count] pairs or ['components' => [...], 'notes' => '...'].
     *
     * @return array{0: list<PhaseComponent>, 1: ?string}
     */
    private function parse(mixed $definition): array
    {
        $notes = null;
        $raw = $definition;

        if (is_array($definition) && array_key_exists('components', $definition)) {
            $raw = $definition['components'];
            $notes = $definition['notes'] ?? null;
        }

        $components = [];

        foreach ((array) $raw as $pair) {
            [$type, $count] = $pair;
            $components[] = PhaseComponent::make($type, (int) $count);
        }

        return [$components, $notes];
    }
}
