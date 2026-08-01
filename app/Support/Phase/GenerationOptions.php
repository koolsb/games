<?php

declare(strict_types=1);

namespace App\Support\Phase;

use App\Enums\Phase\DifficultyBand;
use App\Enums\Phase\GenerationMode;

final readonly class GenerationOptions
{
    /**
     * @param  list<DifficultyBand>|null  $slotBands  one band per slot (MANUAL mode)
     */
    public function __construct(
        public int $count = 10,
        public GenerationMode $mode = GenerationMode::RAMP,
        public ?DifficultyBand $band = null,
        public ?array $slotBands = null,
        public ?int $seed = null,
        public bool $allowDuplicates = false,
        public ?DifficultyBand $minBand = null,
        public ?DifficultyBand $maxBand = null,
    ) {}

    public function withSeed(?int $seed): self
    {
        return new self($this->count, $this->mode, $this->band, $this->slotBands, $seed, $this->allowDuplicates, $this->minBand, $this->maxBand);
    }
}
