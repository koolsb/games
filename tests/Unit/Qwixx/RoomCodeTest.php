<?php

declare(strict_types=1);

use App\Support\Qwixx\RoomCode;

it('generates four-character codes from the room alphabet', function () {
    foreach (range(1, 200) as $ignored) {
        $code = RoomCode::generate();

        expect($code)->toHaveLength(4)
            ->and($code)->toMatch('/^[A-Z2-7]{4}$/')
            ->and(RoomCode::isValid($code))->toBeTrue();
    }
});

it('leaves out the characters people mistype', function () {
    // Base32 has no 0 or 1; I and O are dropped because they are read back
    // as those digits when someone calls a code across the table.
    expect(RoomCode::ALPHABET)->not->toContain('I')
        ->and(RoomCode::ALPHABET)->not->toContain('O')
        ->and(RoomCode::ALPHABET)->not->toContain('0')
        ->and(RoomCode::ALPHABET)->not->toContain('1')
        ->and(strlen(RoomCode::ALPHABET))->toBe(30);

    foreach (range(1, 200) as $ignored) {
        expect(RoomCode::generate())->not->toContain('I')->not->toContain('O');
    }
});

it('uses more than one character', function () {
    // A broken generator that returns the same letter every time would pass
    // every assertion above.
    $codes = array_map(fn (): string => RoomCode::generate(), range(1, 100));

    expect(count(array_unique($codes)))->toBeGreaterThan(90);
});

it('normalises how a code was typed', function () {
    expect(RoomCode::normalize(' abcd '))->toBe('ABCD')
        ->and(RoomCode::normalize('ab-cd'))->toBe('ABCD')
        ->and(RoomCode::normalize('a b c d'))->toBe('ABCD');
});

it('rejects anything that is not a code', function () {
    expect(RoomCode::isValid('ABC'))->toBeFalse()
        ->and(RoomCode::isValid('ABCDE'))->toBeFalse()
        ->and(RoomCode::isValid('AB0D'))->toBeFalse()
        ->and(RoomCode::isValid('ABID'))->toBeFalse()
        ->and(RoomCode::isValid('AB D'))->toBeFalse()
        ->and(RoomCode::isValid(''))->toBeFalse();
});
