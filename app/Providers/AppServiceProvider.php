<?php

namespace App\Providers;

use App\Services\Phase\PhaseLibrary;
use App\Services\Qwixx\LayoutLibrary;
use App\Services\Qwixx\RoomStore;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Each game's library is a singleton built from its own config file —
     * both are pure in-memory data, so there is nothing else to boot. The
     * Qwixx room store is the one piece of server-side game state, and it
     * lives in the cache rather than a database.
     */
    public function register(): void
    {
        $this->app->singleton(LayoutLibrary::class, fn (): LayoutLibrary => new LayoutLibrary(
            config: config('qwixx', []),
        ));

        $this->app->singleton(PhaseLibrary::class, fn (): PhaseLibrary => new PhaseLibrary(
            config: config('phases', []),
        ));

        $this->app->singleton(RoomStore::class, fn (): RoomStore => new RoomStore(
            config: config('qwixx.multiplayer', []),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
