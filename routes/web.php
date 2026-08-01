<?php

declare(strict_types=1);

use App\Http\Controllers\Phase\PrintController;
use App\Services\Qwixx\LayoutLibrary;
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
});

Route::prefix('phase10')->name('phase10.')->group(function () {
    Route::livewire('/', 'phase10::generator')->name('generator');
    Route::get('/print', PrintController::class)->name('print');
});
