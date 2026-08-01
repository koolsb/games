<?php

declare(strict_types=1);

namespace App\Enums\Phase;

enum GenerationMode: string
{
    /** Difficulty ramps easy→brutal across the slots, like the original game. */
    case RAMP = 'ramp';

    /** Every slot drawn from a single chosen band. */
    case FLAT = 'flat';

    /** The caller chooses a band for each slot. */
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::RAMP => 'Classic ramp',
            self::FLAT => 'Flat difficulty',
            self::MANUAL => 'Manual per phase',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::RAMP => 'Phases get harder as the game goes on.',
            self::FLAT => 'Every phase sits in the same difficulty band.',
            self::MANUAL => 'Pick the difficulty for each phase yourself.',
        };
    }
}
