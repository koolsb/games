<?php

declare(strict_types=1);

namespace App\Support\Phase;

use App\Enums\Phase\ComponentType;

final readonly class PhaseComponent
{
    public function __construct(
        public ComponentType $type,
        public int $count,
    ) {}

    public static function make(ComponentType|string $type, int $count): self
    {
        return new self(
            $type instanceof ComponentType ? $type : ComponentType::from($type),
            $count,
        );
    }

    /**
     * @param  array{type: string, count: int|string}  $data
     */
    public static function fromArray(array $data): self
    {
        return self::make($data['type'], (int) $data['count']);
    }

    /**
     * Human label for `$multiplier` copies of this component.
     */
    public function label(int $multiplier = 1): string
    {
        return $this->type->strategy()->label($this->count, $multiplier);
    }

    /**
     * Difficulty contribution of a single copy of this component.
     */
    public function difficulty(): float
    {
        return $this->type->strategy()->difficulty($this->count);
    }

    /**
     * Stable identity used for grouping, sorting and signatures.
     */
    public function key(): string
    {
        return $this->type->value.':'.$this->count;
    }

    /**
     * @return array{type: string, count: int}
     */
    public function toArray(): array
    {
        return ['type' => $this->type->value, 'count' => $this->count];
    }
}
