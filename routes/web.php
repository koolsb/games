<?php

declare(strict_types=1);

use App\Http\Controllers\Phase\PrintController;
use App\Http\Controllers\Qwixx\RoomController;
use App\Http\Controllers\Scoreboard\BoardController;
use App\Services\Qwixx\LayoutLibrary;
use App\Services\Qwixx\RoomStore;
use App\Services\Scoreboard\BoardStore;
use App\Support\Phase\ScoreRoomCode;
use App\Support\Qwixx\RoomCode;
use Illuminate\Support\Facades\Route;

/*
| Every game is mounted under its own path prefix and route-name prefix so
| the root can stay a plain menu. A game's landing page is its prefix root
| (/qwixx, /phase10) — that is what the shell's "All games" control and the
| menu cards link to.
*/

Route::get('/', function (LayoutLibrary $layouts) {
    return view('home', ['qwixxLayout' => $layouts->find('classic')]);
})->name('home');

Route::prefix('qwixx')->name('qwixx.')->group(function () {
    Route::get('/', function (LayoutLibrary $library) {
        return view('qwixx.picker', ['layouts' => $library->all()]);
    })->name('picker');

    Route::get('/play/{layout}/{mode?}', function (LayoutLibrary $library, string $layout, string $mode = 'solo') {
        abort_unless(in_array($mode, ['solo', 'duo'], true), 404);

        $layout = $library->find($layout) ?? abort(404);

        return view('qwixx.game', ['layout' => $layout, 'mode' => $mode]);
    })->name('game');

    /*
    | One URL carries a multiplayer game from lobby to results — joining,
    | playing and the final scores are states of the same screen, so nobody
    | navigates mid-game and a shared link always lands somewhere sensible.
    */
    Route::get('/room/{code}', function (LayoutLibrary $library, RoomStore $rooms, string $code) {
        $code = RoomCode::normalize($code);

        abort_unless(RoomCode::isValid($code), 404);

        $room = $rooms->find($code) ?? abort(404);
        $layout = $library->find($room->layoutId) ?? abort(404);

        return view('qwixx.room', ['layout' => $layout, 'room' => $room]);
    })->name('room');

    /*
    | The room API. Throttled because it is open to anyone on the internet:
    | creating and joining are cheap to abuse, while syncing is the polling
    | heartbeat. The sync allowance is generous on purpose — the limiter
    | counts per IP, and a table of players sitting on one home connection
    | is a single IP polling every couple of seconds each.
    */
    Route::prefix('rooms')->name('rooms.')->group(function () {
        Route::post('/', [RoomController::class, 'store'])->middleware('throttle:20,1')->name('store');
        Route::get('/{code}', [RoomController::class, 'show'])->middleware('throttle:120,1')->name('show');
        Route::post('/{code}/join', [RoomController::class, 'join'])->middleware('throttle:20,1')->name('join');
        Route::post('/{code}/sync', [RoomController::class, 'sync'])->middleware('throttle:900,1')->name('sync');
        Route::post('/{code}/start', [RoomController::class, 'start'])->middleware('throttle:20,1')->name('start');
        Route::post('/{code}/restart', [RoomController::class, 'restart'])->middleware('throttle:20,1')->name('restart');
    });
});

Route::prefix('phase10')->name('phase10.')->group(function () {
    Route::livewire('/', 'phase10::generator')->name('generator');
    Route::get('/print', PrintController::class)->name('print');

    /*
    | One URL carries a scoring game from setup to the final standings —
    | same reasoning as Qwixx's room route. It's a plain chromeless view
    | (like qwixx.room) hosting a single Livewire component that does all
    | the work; the host is whoever holds the matching
    | `phase10_host_{code}` cookie set at creation, anyone else who opens
    | the link gets a read-only, polling view.
    */
    Route::get('/play/{code?}', function (?string $code = null) {
        if ($code !== null) {
            $normalized = ScoreRoomCode::normalize($code);

            abort_unless(ScoreRoomCode::isValid($normalized), 404);

            /*
            | Codes get shouted across a table and typed back in, so a link
            | that arrives lowercased or hyphenated should still work —
            | redirect it to the canonical one rather than 404 on casing.
            */
            if ($normalized !== $code) {
                return redirect()->route('phase10.play', $normalized);
            }
        }

        return view('phase10.play', ['code' => $code]);
    })->name('play');
});

Route::prefix('scoreboard')->name('scoreboard.')->group(function () {
    Route::view('/', 'scoreboard.index')->name('index');

    /*
    | The play screen. With no code it opens this device's current game —
    | which may not have a code yet, if it was started with no signal. With
    | a code, the browser decides whether it is the scorekeeper (it holds
    | that game's token in localStorage) or a follower; the server can't
    | know, and doesn't need to. The code is not checked against the store
    | here, because the scorekeeper must be able to reopen their own game
    | while offline or after the server copy has expired.
    */
    Route::get('/play/{code?}', function (BoardStore $boards, ?string $code = null) {
        if ($code !== null) {
            $normalized = ScoreRoomCode::normalize($code);

            abort_unless(ScoreRoomCode::isValid($normalized), 404);

            if ($normalized !== $code) {
                return redirect()->route('scoreboard.play', $normalized);
            }
        }

        // The server's copy, if it has one, so a follower sees the scores on
        // first paint instead of waiting for the first poll.
        $board = $code !== null ? $boards->find($code) : null;

        return view('scoreboard.play', ['code' => $code, 'board' => $board?->toClientArray()]);
    })->name('play');

    /*
    | The scoreboard API, throttled like the Qwixx room API. Showing is the
    | followers' polling heartbeat; updating is the scorekeeper's push,
    | debounced in the browser but still a burst after a spell offline.
    */
    Route::prefix('games')->name('games.')->group(function () {
        Route::post('/', [BoardController::class, 'store'])->middleware('throttle:20,1')->name('store');
        Route::get('/{code}', [BoardController::class, 'show'])->middleware('throttle:120,1')->name('show');
        Route::put('/{code}', [BoardController::class, 'update'])->middleware('throttle:300,1')->name('update');
    });
});
