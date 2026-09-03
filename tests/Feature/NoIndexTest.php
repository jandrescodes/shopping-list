<?php

use App\Models\ShoppingList;

it('sends X-Robots-Tag on the list page', function () {
    $list = ShoppingList::factory()->create();

    $this->get("/l/{$list->slug}")
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('returns 404 for an unknown list slug', function () {
    $this->get('/l/does-not-exist')->assertNotFound();
});

it('disallows /l/ in robots.txt', function () {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)->toContain('Disallow: /l/');
});
