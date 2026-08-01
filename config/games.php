<?php

/*
|--------------------------------------------------------------------------
| The game registry — one entry per game mounted under its own path.
|--------------------------------------------------------------------------
|
| The root menu renders this list in order, and the shared shell reads the
| current game's entry to brand the header (wordmark, stripe, accent) and
| to link its "Rules" button. Adding a game means adding an entry here, a
| route group in routes/web.php, and a wordmark case in
| resources/views/components/site/wordmark.blade.php.
|
| 'route'  — the game's landing page, i.e. where the header wordmark and the
|            menu card point. Every game must have one, because that landing
|            page is what the "All games" control returns from.
| 'theme'  — class applied to <html>, selecting the accent color in app.css.
| 'header' — background utility for the header bar.
| 'stripe' — the color band under the header.
*/

return [

    'games' => [

        'qwixx' => [
            'name' => 'Qwixx',
            'tagline' => 'Scoresheets',
            'summary' => 'A digital scoresheet for the Qwixx dice game. Tap to cross out numbers, '
                .'the left-to-right rule is enforced, and scores tally themselves.',
            'route' => 'qwixx.picker',
            'rules_url' => 'https://gamewright.com/pdfs/Rules/QwixxTM-RULES.pdf',
            'theme' => 'theme-qwixx',
            'header' => 'bg-zinc-900',
            'stripe' => 'qwixx-stripe',
        ],

        'phase10' => [
            'name' => 'Phase 10',
            'tagline' => 'Game Generator',
            'summary' => 'Generate fresh Phase 10 games from a difficulty-weighted library of '
                .'hundreds of phases, then tweak the list and print a card.',
            'route' => 'phase10.generator',
            'rules_url' => 'https://en.wikipedia.org/wiki/Phase_10',
            'theme' => 'theme-phase10',
            'header' => 'phase10-card-bg',
            'stripe' => 'phase10-stripe',
        ],

    ],

];
