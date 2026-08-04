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
        ->assertSee('Start scoring');
});

it('shows a friendly message for a game code that does not exist', function (): void {
    $this->get('/phase10/play/ZZZZ')
        ->assertOk()
        ->assertSee('This game is not here');
});

it('lets the host start a scoring game from the setup screen', function (): void {
    Livewire::test('phase10-play')
        ->set('setupNames', ['Alice', 'Bob'])
        ->call('useClassicPhases')
        ->call('startScoring')
        ->assertRedirect();
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
