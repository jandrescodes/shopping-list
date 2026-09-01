<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Every feature test runs against a fresh in-memory SQLite database
| (see phpunit.xml). Browser-level checks for the client layer are run
| separately with Playwright, not through a Pest browser plugin.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
