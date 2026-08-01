<?php

/*
|--------------------------------------------------------------------------
| Qwixx scoresheet layouts — this config file IS the database.
|--------------------------------------------------------------------------
|
| Each layout has exactly 4 rows. A row is either a solid color row
| ('color' + 'numbers', lock takes the row color) or a per-cell row
| ('lock' + 'cells' of [number, color] pairs). Rows play left to right
| by position regardless of the printed numbers. LayoutFactory validates
| every layout on boot: 4 rows, each row a permutation of 2-12, every
| color owning exactly 11 cells and exactly one lock.
|
| The variant sheets are transcribed from the physical double-sided
| "Mixed Colors / Mixed Numbers" Qwixx score card.
*/

return [

    'layouts' => [

        'classic' => [
            'name' => 'Classic',
            'description' => 'The original scoresheet. Red and yellow climb 2-12, green and blue descend 12-2.',
            'rows' => [
                ['color' => 'red', 'numbers' => [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]],
                ['color' => 'yellow', 'numbers' => [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]],
                ['color' => 'green', 'numbers' => [12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2]],
                ['color' => 'blue', 'numbers' => [12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2]],
            ],
        ],

        'mixed-numbers' => [
            'name' => 'Mixed Numbers',
            'description' => 'Solid color rows with scrambled numbers. Crosses still march left to right.',
            'rows' => [
                ['color' => 'red', 'numbers' => [10, 6, 2, 8, 3, 4, 12, 5, 9, 7, 11]],
                ['color' => 'yellow', 'numbers' => [9, 12, 4, 6, 7, 2, 5, 8, 11, 3, 10]],
                ['color' => 'green', 'numbers' => [8, 2, 10, 12, 6, 9, 7, 4, 5, 11, 3]],
                ['color' => 'blue', 'numbers' => [5, 7, 11, 9, 3, 12, 2, 8, 10, 6, 4]],
            ],
        ],

        'mixed-colors' => [
            'name' => 'Mixed Colors',
            'description' => 'Numbers run in order but colors are scattered. Each row still scores on its own count of marks.',
            'rows' => [
                ['lock' => 'red', 'cells' => [
                    [2, 'yellow'], [3, 'yellow'], [4, 'yellow'],
                    [5, 'blue'], [6, 'blue'], [7, 'blue'],
                    [8, 'green'], [9, 'green'], [10, 'green'],
                    [11, 'red'], [12, 'red'],
                ]],
                ['lock' => 'yellow', 'cells' => [
                    [2, 'red'], [3, 'red'],
                    [4, 'green'], [5, 'green'], [6, 'green'], [7, 'green'],
                    [8, 'blue'], [9, 'blue'],
                    [10, 'yellow'], [11, 'yellow'], [12, 'yellow'],
                ]],
                ['lock' => 'green', 'cells' => [
                    [12, 'blue'], [11, 'blue'], [10, 'blue'],
                    [9, 'yellow'], [8, 'yellow'], [7, 'yellow'],
                    [6, 'red'], [5, 'red'], [4, 'red'],
                    [3, 'green'], [2, 'green'],
                ]],
                ['lock' => 'blue', 'cells' => [
                    [12, 'green'], [11, 'green'],
                    [10, 'red'], [9, 'red'], [8, 'red'], [7, 'red'],
                    [6, 'yellow'], [5, 'yellow'],
                    [4, 'blue'], [3, 'blue'], [2, 'blue'],
                ]],
            ],
        ],

    ],

];
