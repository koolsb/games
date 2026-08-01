<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Phase 10 phase library
|--------------------------------------------------------------------------
|
| This file IS the phase database. Add, remove or tweak phases here and
| redeploy — there is no admin UI and no database.
|
| A phase is a list of requirement components. Each component is a
| [type, count] pair. Available types:
|
|   set          N cards of the same number          e.g. ['set', 3]
|   run          N consecutive numbers                e.g. ['run', 7]
|   color        N cards of one color                 e.g. ['color', 7]
|   color_run    a run of N, all one color            e.g. ['color_run', 5]
|   evens        N even-numbered cards                 e.g. ['evens', 4]
|   odds         N odd-numbered cards                  e.g. ['odds', 4]
|   color_evens  N even cards of one color             e.g. ['color_evens', 4]
|   color_odds   N odd cards of one color              e.g. ['color_odds', 4]
|
| Two identical components make a "2 sets of 3" style phase:
|   [['set', 3], ['set', 3]]   => "2 sets of 3"
|
| A custom phase may also be written as an array with notes:
|   ['components' => [['run', 7]], 'notes' => 'Crowd favorite']
|
| Difficulty is computed automatically from the components.
|
*/

return [

    /*
    | The 10 phases printed on the official Phase 10 card.
    */
    'classics' => [
        [['set', 3], ['set', 3]],            // 1.  2 sets of 3
        [['set', 3], ['run', 4]],            // 2.  1 set of 3 + 1 run of 4
        [['set', 4], ['run', 4]],            // 3.  1 set of 4 + 1 run of 4
        [['run', 7]],                        // 4.  1 run of 7
        [['run', 8]],                        // 5.  1 run of 8
        [['run', 9]],                        // 6.  1 run of 9
        [['set', 4], ['set', 4]],            // 7.  2 sets of 4
        [['color', 7]],                      // 8.  7 cards of one color
        [['set', 5], ['set', 2]],            // 9.  1 set of 5 + 1 set of 2
        [['set', 5], ['set', 3]],            // 10. 1 set of 5 + 1 set of 3
    ],

    /*
    | Hand-curated extra phases. Same format as classics (and may use the
    | ['components' => [...], 'notes' => '...'] form). Empty by default.
    */
    'custom' => [
        // ['components' => [['color_run', 5]], 'notes' => 'Rainbow nightmare'],
    ],

    /*
    | Automatic enumeration fills the library with hundreds of weighted
    | variants so every difficulty band has plenty of options.
    */
    'enumeration' => [
        'enabled' => true,
        'max_total_cards' => 10,
        'two_component' => true,
        'three_component' => true,
        // Restrict which component types get enumerated (omit to allow all):
        // 'types' => ['set', 'run', 'color', 'color_run', 'evens', 'odds', 'color_evens', 'color_odds'],
    ],

];
