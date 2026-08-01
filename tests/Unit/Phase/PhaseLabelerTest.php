<?php

declare(strict_types=1);

use App\Support\Phase\PhaseComponent;
use App\Support\Phase\PhaseLabeler;

function pc(string $type, int $count): PhaseComponent
{
    return PhaseComponent::make($type, $count);
}

beforeEach(function (): void {
    $this->labeler = new PhaseLabeler;
});

it('renders the ten classic phase labels exactly', function (array $components, string $expected): void {
    expect($this->labeler->label($components))->toBe($expected);
})->with([
    'phase 1' => [[pc('set', 3), pc('set', 3)], '2 sets of 3'],
    'phase 2' => [[pc('set', 3), pc('run', 4)], '1 set of 3 + 1 run of 4'],
    'phase 3' => [[pc('set', 4), pc('run', 4)], '1 set of 4 + 1 run of 4'],
    'phase 4' => [[pc('run', 7)], '1 run of 7'],
    'phase 5' => [[pc('run', 8)], '1 run of 8'],
    'phase 6' => [[pc('run', 9)], '1 run of 9'],
    'phase 7' => [[pc('set', 4), pc('set', 4)], '2 sets of 4'],
    'phase 8' => [[pc('color', 7)], '7 cards of one color'],
    'phase 9' => [[pc('set', 5), pc('set', 2)], '1 set of 5 + 1 set of 2'],
    'phase 10' => [[pc('set', 5), pc('set', 3)], '1 set of 5 + 1 set of 3'],
]);

it('collapses adjacent identical components into a multiplier', function (): void {
    expect($this->labeler->label([pc('set', 4), pc('set', 4)]))->toBe('2 sets of 4')
        ->and($this->labeler->label([pc('run', 5), pc('run', 5)]))->toBe('2 runs of 5');
});

it('does not collapse components of differing size', function (): void {
    expect($this->labeler->label([pc('set', 5), pc('set', 2)]))->toBe('1 set of 5 + 1 set of 2');
});

it('labels color and parity groups without a leading count', function (): void {
    expect($this->labeler->label([pc('color', 6)]))->toBe('6 cards of one color')
        ->and($this->labeler->label([pc('evens', 4)]))->toBe('4 even cards')
        ->and($this->labeler->label([pc('color_run', 5)]))->toBe('1 run of 5 of one color');
});
