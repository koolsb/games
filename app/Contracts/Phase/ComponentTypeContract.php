<?php

declare(strict_types=1);

namespace App\Contracts\Phase;

/**
 * One requirement-component type (set, run, color group, evens, …).
 *
 * To add a new requirement type: create a class implementing this contract
 * under App\Support\Phase\Types, add a case to App\Enums\Phase\ComponentType,
 * and wire it in ComponentType::strategy().
 */
interface ComponentTypeContract
{
    /**
     * Full human label for `$multiplier` identical components of size `$count`,
     * e.g. (count: 3, multiplier: 2) => "2 sets of 3"; (count: 7, multiplier: 1)
     * => "7 cards of one color".
     */
    public function label(int $count, int $multiplier): string;

    /**
     * Difficulty contribution of a single component of this type and size,
     * including the deck-scarcity penalty. Larger = harder.
     */
    public function difficulty(int $count): float;

    /**
     * Whether this type carries a single-color constraint.
     */
    public function colorConstrained(): bool;

    /**
     * Whether this type is "run-like" (consecutive numbers) vs "set-like".
     * Used by the scorer to nudge set+run combos.
     */
    public function isRunLike(): bool;

    /**
     * Valid component sizes when enumerating the phase library.
     *
     * @return list<int>
     */
    public function enumerationRange(): array;
}
