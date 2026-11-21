<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Scoreboard — the generic score tracker
|--------------------------------------------------------------------------
|
| The scorekeeper's device owns the game (it lives in that browser's
| localStorage); the server holds a copy only so other people can follow
| along with the code. Like the other games' shared state, it sits in the
| cache under a sliding TTL that every write refreshes.
|
*/

return [

    'store' => env('SCOREBOARD_CACHE', 'file'),

    'ttl_hours' => 72,

    'max_players' => 12,

    'name_max' => 20,

    'title_max' => 40,

    // Every tap is an entry; this is a few long evenings of a counting game.
    'max_entries' => 5000,

    // A single entry's points, either way. Generous — it only exists so a
    // stray paste can't put a 30-digit number on everyone's screen.
    'max_points' => 1000000,

];
