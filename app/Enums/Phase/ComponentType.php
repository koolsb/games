<?php

declare(strict_types=1);

namespace App\Enums\Phase;

use App\Contracts\Phase\ComponentTypeContract;
use App\Support\Phase\Types\ColorEvensType;
use App\Support\Phase\Types\ColorOddsType;
use App\Support\Phase\Types\ColorRunType;
use App\Support\Phase\Types\ColorType;
use App\Support\Phase\Types\EvensType;
use App\Support\Phase\Types\OddsType;
use App\Support\Phase\Types\RunType;
use App\Support\Phase\Types\SetType;

enum ComponentType: string
{
    case SET = 'set';
    case RUN = 'run';
    case COLOR = 'color';
    case COLOR_RUN = 'color_run';
    case EVENS = 'evens';
    case ODDS = 'odds';
    case COLOR_EVENS = 'color_evens';
    case COLOR_ODDS = 'color_odds';

    /**
     * The strategy that owns labelling, scoring and enumeration for this type.
     * This match is the single place to wire a new requirement type.
     */
    public function strategy(): ComponentTypeContract
    {
        static $cache = [];

        return $cache[$this->value] ??= match ($this) {
            self::SET => new SetType,
            self::RUN => new RunType,
            self::COLOR => new ColorType,
            self::COLOR_RUN => new ColorRunType,
            self::EVENS => new EvensType,
            self::ODDS => new OddsType,
            self::COLOR_EVENS => new ColorEvensType,
            self::COLOR_ODDS => new ColorOddsType,
        };
    }

    /**
     * Short human name for admin/config dropdowns.
     */
    public function label(): string
    {
        return match ($this) {
            self::SET => 'Set',
            self::RUN => 'Run',
            self::COLOR => 'Cards of one color',
            self::COLOR_RUN => 'Run of one color',
            self::EVENS => 'Even cards',
            self::ODDS => 'Odd cards',
            self::COLOR_EVENS => 'Even cards of one color',
            self::COLOR_ODDS => 'Odd cards of one color',
        };
    }
}
