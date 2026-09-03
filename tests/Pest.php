<?php

use App\Models\ShoppingList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Every feature test runs against a fresh MySQL database defined in
| .env.testing (copy .env.testing.example). MySQL, not SQLite, so the
| row locks in App\Support\ListVersion and the real column types are
| exercised. Browser-level checks for the client layer are run
| separately with Playwright, not through a Pest browser plugin.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->withoutVite())
    ->afterEach(fn () => ShoppingList::$slugGenerator = null)
    ->in('Feature');
