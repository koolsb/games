<?php

declare(strict_types=1);

use App\Services\Phase\PhaseLibrary;
use App\Services\Phase\ScoreRoomStore;
use App\Support\Phase\Phase;
use App\Support\Phase\ScoreRoom;
use Livewire\Livewire;

function createScoreRoom(array $playerNames = ['Alice', 'Bob']): ScoreRoom
{
    $signatures = app(PhaseLibrary::class)->classics()->map(fn (Phase $p): string => $p->signature)->all();

    return app(ScoreRoomStore::class)->create($signatures, $playerNames);
}

it('renders the setup screen with no code', function (): void {
    $this->get('/phase10/play')
        ->assertOk()
        ->assertSee('Keep score');
});

it('shows a friendly message for a game code that does not exist', function (): void {
    $this->get('/phase10/play/ZZZZ')
        ->assertOk()
        ->assertSee('This game is not here');
});

it('sends a mistyped-case game link to the canonical code', function (): void {
    $room = createScoreRoom();

    $this->get('/phase10/play/'.strtolower($room->code))
        ->assertRedirect('/phase10/play/'.$room->code);
});

it('rejects a game code that could never have been issued', function (): void {
    $this->get('/phase10/play/nope!')->assertNotFound();
});

it('lets the host start a scoring game from the setup screen', function (): void {
    Livewire::test('phase10-play')
        ->set('setupNames', ['Alice', 'Bob'])
        ->call('useClassicPhases')
        ->call('startScoring')
        ->assertRedirect();
});

it('carries names and phases in from the setup URL', function (): void {
    Livewire::withQueryParams(['players' => 'Alice,Bob,Cara'])
        ->test('phase10-play')
        ->assertSet('setupNames', ['Alice', 'Bob', 'Cara']);
});

it('explains what is missing instead of a dead Start button', function (): void {
    Livewire::test('phase10-play')
        ->set('setupNames', ['Alice', 'Bob'])
        ->call('startScoring')
        ->assertSet('setupError', 'Pick the phases you are playing first.')
        ->assertNoRedirect();

    Livewire::test('phase10-play')
        ->call('useClassicPhases')
        ->call('startScoring')
        ->assertSet('setupError', 'Name at least two players.')
        ->assertNoRedirect();
});

it('sends a watcher to a game that exists and says so when it does not', function (): void {
    $room = createScoreRoom();

    Livewire::test('phase10-play')
        ->set('watchCode', strtolower($room->code))
        ->call('watchGame')
        ->assertRedirect('/phase10/play/'.$room->code);

    Livewire::test('phase10-play')
        ->set('watchCode', 'ZZZZ')
        ->call('watchGame')
        ->assertNoRedirect()
        ->assertSet('watchError', "There's no game running with the code ZZZZ.");
});

it('starts every score at zero so a blank hand needs no typing', function (): void {
    $room = createScoreRoom();
    [$alice, $bob] = $room->players;

    Livewire::withCookie("phase10_host_{$room->code}", $room->hostToken);

    Livewire::test('phase10-play', ['code' => $room->code])
        ->assertSet("pending.{$alice->id}.score", '0')
        ->call('toggleCompleted', $alice->id)
        ->call('finalizeRound');

    $updated = app(ScoreRoomStore::class)->find($room->code);

    expect($updated->player($alice->id)->totalScore())->toBe(0)
        ->and($updated->player($alice->id)->phasesCompleted())->toBe(1)
        ->and($updated->player($bob->id)->totalScore())->toBe(0)
        ->and($updated->player($bob->id)->phasesCompleted())->toBe(0);
});

it('nudges a score in fives and never below zero', function (): void {
    $room = createScoreRoom();
    [$alice] = $room->players;

    Livewire::withCookie("phase10_host_{$room->code}", $room->hostToken);

    Livewire::test('phase10-play', ['code' => $room->code])
        ->call('bumpScore', $alice->id, 5)
        ->call('bumpScore', $alice->id, 5)
        ->assertSet("pending.{$alice->id}.score", '10')
        ->call('bumpScore', $alice->id, -5)
        ->call('bumpScore', $alice->id, -5)
        ->call('bumpScore', $alice->id, -5)
        ->assertSet("pending.{$alice->id}.score", '0');
});

it('reads an emptied or junk score box as zero', function (): void {
    $room = createScoreRoom();
    [$alice] = $room->players;

    Livewire::withCookie("phase10_host_{$room->code}", $room->hostToken);

    Livewire::test('phase10-play', ['code' => $room->code])
        ->set("pending.{$alice->id}.score", '')
        ->assertSet("pending.{$alice->id}.score", '0')
        ->set("pending.{$alice->id}.score", '4x5')
        ->assertSet("pending.{$alice->id}.score", '45');
});

it('lets the host restart the same table on a fresh code', function (): void {
    $room = createScoreRoom(['Alice', 'Bob']);

    Livewire::withCookie("phase10_host_{$room->code}", $room->hostToken);

    Livewire::test('phase10-play', ['code' => $room->code])
        ->call('rematch')
        ->assertRedirect();

    // The finished game is still readable on its own link.
    expect(app(ScoreRoomStore::class)->find($room->code))->not->toBeNull();
});

it('does not let a spectator restart the game', function (): void {
    $room = createScoreRoom();

    Livewire::test('phase10-play', ['code' => $room->code])
        ->call('rematch')
        ->assertNoRedirect();
});

it('recognizes the host via their cookie and lets them finalize a round', function (): void {
    $room = createScoreRoom();
    [$alice, $bob] = $room->players;

    Livewire::withCookie("phase10_host_{$room->code}", $room->hostToken);

    Livewire::test('phase10-play', ['code' => $room->code])
        ->assertSet('isHost', true)
        ->set("pending.{$alice->id}.score", '10')
        ->set("pending.{$alice->id}.completed", true)
        ->set("pending.{$bob->id}.score", '25')
        ->set("pending.{$bob->id}.completed", false)
        ->call('finalizeRound');

    $updated = app(ScoreRoomStore::class)->find($room->code);

    expect($updated->player($alice->id)->totalScore())->toBe(10)
        ->and($updated->player($alice->id)->phasesCompleted())->toBe(1)
        ->and($updated->player($bob->id)->totalScore())->toBe(25);
});

it('treats a visitor without the host cookie as read-only', function (): void {
    $room = createScoreRoom();

    Livewire::test('phase10-play', ['code' => $room->code])
        ->assertSet('isHost', false)
        ->call('finalizeRound')
        ->call('undoLastRound');

    $unchanged = app(ScoreRoomStore::class)->find($room->code);

    expect($unchanged->status)->toBe(ScoreRoom::ACTIVE)
        ->and($unchanged->players[0]->totalScore())->toBe(0);
});

it('rejects mutations from a browser holding the wrong host token', function (): void {
    $room = createScoreRoom();

    Livewire::withCookie("phase10_host_{$room->code}", 'not-the-real-token');

    Livewire::test('phase10-play', ['code' => $room->code])
        ->assertSet('isHost', false);
});
