<?php

declare(strict_types=1);

namespace App\Support\Phase;

use Illuminate\Support\Str;

/**
 * One player's running total in a scoring game. `log` holds one entry per
 * finalized round — {score, completed} — which is all that's needed to
 * derive the running total and how many phases have been completed;
 * "undo last round" just pops the last entry.
 */
final readonly class ScorePlayer
{
    /**
     * @param  list<array{score: int, completed: bool}>  $log
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $log = [],
    ) {}

    public static function make(string $name): self
    {
        return new self(id: (string) Str::uuid(), name: $name);
    }

    public function totalScore(): int
    {
        return array_sum(array_column($this->log, 'score'));
    }

    public function phasesCompleted(): int
    {
        return count(array_filter($this->log, static fn (array $round): bool => $round['completed']));
    }

    public function currentPhase(int $phaseCount): int
    {
        return min($this->phasesCompleted() + 1, max($phaseCount, 1));
    }

    public function finished(int $phaseCount): bool
    {
        return $this->phasesCompleted() >= $phaseCount;
    }

    public function withName(string $name): self
    {
        return new self($this->id, $name, $this->log);
    }

    public function withRound(int $score, bool $completed): self
    {
        return new self($this->id, $this->name, [...$this->log, ['score' => $score, 'completed' => $completed]]);
    }

    public function withoutLastRound(): self
    {
        $log = $this->log;
        array_pop($log);

        return new self($this->id, $this->name, $log);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'log' => $this->log,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            name: (string) $data['name'],
            log: array_map(
                static fn (array $round): array => ['score' => (int) $round['score'], 'completed' => (bool) $round['completed']],
                (array) ($data['log'] ?? []),
            ),
        );
    }
}
