<?php

namespace App\Providers;

use App\Services\Phase\PhaseLibrary;
use App\Services\Qwixx\LayoutLibrary;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Each game's library is a singleton built from its own config file —
     * both are pure in-memory data, so there is nothing else to boot.
     */
    public function register(): void
    {
        $this->app->singleton(LayoutLibrary::class, fn (): LayoutLibrary => new LayoutLibrary(
            config: config('qwixx', []),
        ));

        $this->app->singleton(PhaseLibrary::class, fn (): PhaseLibrary => new PhaseLibrary(
            config: config('phases', []),
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
