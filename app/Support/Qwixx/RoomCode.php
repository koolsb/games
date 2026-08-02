<?php

declare(strict_types=1);

namespace App\Support\Qwixx;

/**
 * The 4-character code players read out to each other to join a room.
 *
 * Base32 (RFC 4648) minus I and O, which people mistype as 1 and 0 — the
 * alphabet already has no 0 or 1, so dropping the lookalikes costs two
 * symbols and buys codes that survive being shouted across a table.
 */
final class RoomCode
{
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ234567';

    public const LENGTH = 4;

    public static function generate(): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    /**
     * Normalises what a player typed — lowercase, stray spaces, the dash
     * some people add. Characters outside the alphabet are left alone so
     * isValid() rejects them rather than guessing at a substitution.
     */
    public static function normalize(string $code): string
    {
        return str_replace([' ', '-'], '', strtoupper(trim($code)));
    }

    public static function isValid(string $code): bool
    {
        return (bool) preg_match('/^['.self::ALPHABET.']{'.self::LENGTH.'}$/', $code);
    }
}
