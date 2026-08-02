<?php

declare(strict_types=1);

use App\Http\Controllers\Phase\PrintController;
use App\Http\Controllers\Qwixx\RoomController;
use App\Services\Qwixx\LayoutLibrary;
use App\Services\Qwixx\RoomStore;
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
});
