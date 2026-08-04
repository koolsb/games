<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Services\Phase\ScoreRoomStore;
use App\Support\Phase\ScoreRoomCode;

/**
 * The "someone else is hosting — let me follow along" box.
 *
 * It lives on both Phase 10 screens a person could plausibly be looking at
 * when the code gets read out (the generator, and the scoring setup
 * screen), so nobody has to guess where to type it. Checking the game
 * exists before redirecting is the whole point: a mistyped code should say
 * so while the host is still in earshot, not land on a dead page.
 */
trait WatchesScoreGames
{
    public string $watchCode = '';

    public ?string $watchError = null;

    public function watchGame(): void
    {
        $code = ScoreRoomCode::normalize($this->watchCode);

        if (! ScoreRoomCode::isValid($code)) {
            $this->watchError = 'A game code is '.ScoreRoomCode::LENGTH.' letters or numbers.';

            return;
        }

        if (app(ScoreRoomStore::class)->find($code) === null) {
            $this->watchError = "There's no game running with the code {$code}.";

            return;
        }

        $this->redirect(route('phase10.play', $code));
    }

    public function updatedWatchCode(): void
    {
        $this->watchError = null;
    }
}
