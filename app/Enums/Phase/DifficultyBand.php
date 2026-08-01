<?php

declare(strict_types=1);

namespace App\Enums\Phase;

enum DifficultyBand: string
{
    case EASY = 'easy';
    case MEDIUM = 'medium';
    case HARD = 'hard';
    case BRUTAL = 'brutal';

    /**
     * Upper score bound (exclusive) for each band. BRUTAL is open-ended.
     */
    private const THRESHOLDS = [
        'easy' => 10.0,
        'medium' => 22.0,
        'hard' => 45.0,
    ];

    public static function fromScore(float $score): self
    {
        return match (true) {
            $score < self::THRESHOLDS['easy'] => self::EASY,
            $score < self::THRESHOLDS['medium'] => self::MEDIUM,
            $score < self::THRESHOLDS['hard'] => self::HARD,
            default => self::BRUTAL,
        };
    }

    /**
     * Bands ordered easiest to hardest.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::EASY, self::MEDIUM, self::HARD, self::BRUTAL];
    }

    /**
     * Zero-based position in the easy→brutal ordering.
     */
    public function index(): int
    {
        return array_search($this, self::ordered(), true);
    }

    /**
     * Inclusive-ish [min, max] score window for the band, used when targeting a
     * slot's ideal score during generation.
     *
     * @return array{float, float}
     */
    public function range(): array
    {
        return match ($this) {
            self::EASY => [0.0, self::THRESHOLDS['easy']],
            self::MEDIUM => [self::THRESHOLDS['easy'], self::THRESHOLDS['medium']],
            self::HARD => [self::THRESHOLDS['medium'], self::THRESHOLDS['hard']],
            self::BRUTAL => [self::THRESHOLDS['hard'], self::THRESHOLDS['hard'] * 1.6],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::EASY => 'Easy',
            self::MEDIUM => 'Medium',
            self::HARD => 'Hard',
            self::BRUTAL => 'Brutal',
        };
    }

    /**
     * A Flux color token for badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::EASY => 'green',
            self::MEDIUM => 'blue',
            self::HARD => 'amber',
            self::BRUTAL => 'red',
        };
    }
}
