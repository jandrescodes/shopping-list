<?php

use App\Models\ShoppingList;

it('sends Referrer-Policy: no-referrer on pages and API responses', function () {
    $list = ShoppingList::factory()->create();

    $this->get("/l/{$list->slug}")->assertHeader('Referrer-Policy', 'no-referrer');
    $this->getJson("/api/lists/{$list->slug}")->assertHeader('Referrer-Policy', 'no-referrer');
});

it('sends Strict-Transport-Security', function () {
    $this->get('/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('does not grant cross-origin access to the API', function () {
    $list = ShoppingList::factory()->create();

    $this->getJson("/api/lists/{$list->slug}", ['Origin' => 'https://evil.example'])
        ->assertOk()
        ->assertHeaderMissing('Access-Control-Allow-Origin');
});
