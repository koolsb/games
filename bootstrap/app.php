<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');

        /*
        | The Qwixx room API carries no ambient authority — a player's only
        | credential is the room token they hold, sent explicitly per call —
        | so there is nothing for a CSRF token to protect. Requiring one
        | would just 419 a tablet that has sat on a lobby past the session
        | lifetime, mid-game.
        */
        // Both patterns: 'qwixx/rooms/*' alone would miss the bare POST that
        // creates a room.
        $middleware->validateCsrfTokens(except: ['qwixx/rooms', 'qwixx/rooms/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'qwixx/rooms', 'qwixx/rooms/*'),
        );
    })->create();
