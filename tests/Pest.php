<?php

declare(strict_types=1);

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the full framework via the application TestCase.
| Unit tests for the pure-PHP phase domain need no framework and use
| Pest's default (PHPUnit\Framework\TestCase) for speed.
|
*/

pest()->extend(TestCase::class)->in('Feature');
