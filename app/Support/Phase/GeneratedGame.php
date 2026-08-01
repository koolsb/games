<?php

declare(strict_types=1);

namespace App\Support\Phase;

use App\Enums\Phase\GenerationMode;

final readonly class GeneratedGame
{
    /**
     * @param  list<Phase>  $phases  ordered phases (slot 1 first)
     * @param  list<int>  $widenedSlots  slot indexes whose target band had to be widened
     */
    public function __construct(
        public array $phases,
        public ?int $seed,
        public GenerationMode $mode,
        public array $widenedSlots = [],
    ) {}

    public function count(): int
    {
        return count($this->phases);
    }

    public function signatures(): array
    {
        return array_map(static fn (Phase $p): string => $p->signature, $this->phases);
    }
}
