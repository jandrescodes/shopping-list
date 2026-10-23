<?php

use App\Models\ShoppingList;

it('throttles list creation at 10 per hour per IP', function () {
    foreach (range(1, 10) as $i) {
        $this->postJson('/api/lists', ['name' => "List {$i}"])->assertCreated();
    }

    $this->postJson('/api/lists', ['name' => 'One too many'])->assertStatus(429);
});

it('throttles sync at 60 per minute per IP', function () {
    $list = ShoppingList::factory()->create();

    foreach (range(1, 60) as $_) {
        $this->getJson("/api/lists/{$list->slug}/items")->assertOk();
    }

    $this->getJson("/api/lists/{$list->slug}/items")->assertStatus(429);
});

it('throttles writes at 120 per minute per IP', function () {
    $list = ShoppingList::factory()->create();

    foreach (range(1, 120) as $i) {
        $this->patchJson("/api/lists/{$list->slug}", ['name' => "Name {$i}"])->assertOk();
    }

    $this->patchJson("/api/lists/{$list->slug}", ['name' => 'Blocked'])->assertStatus(429);
});

it('does not throttle reads of a single list', function () {
    $list = ShoppingList::factory()->create();

    foreach (range(1, 30) as $_) {
        $this->getJson("/api/lists/{$list->slug}")->assertOk();
    }
});

it('spends the list-creation allowance when duplicating a list', function () {
    $list = ShoppingList::factory()->create();

    foreach (range(1, 5) as $i) {
        $this->postJson('/api/lists', ['name' => "List {$i}"])->assertCreated();
        $this->postJson("/api/lists/{$list->slug}/duplicate")->assertCreated();
    }

    $this->postJson("/api/lists/{$list->slug}/duplicate")
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('answers 429 with Retry-After once duplicating exceeds 10 per hour', function () {
    $list = ShoppingList::factory()->create();

    foreach (range(1, 10) as $_) {
        $this->postJson("/api/lists/{$list->slug}/duplicate")->assertCreated();
    }

    $response = $this->postJson("/api/lists/{$list->slug}/duplicate")->assertStatus(429);

    expect((int) $response->headers->get('Retry-After'))->toBeGreaterThan(0)
        ->and(ShoppingList::count())->toBe(11);
});

it('spends the allowance even for a slug that does not exist', function () {
    $list = ShoppingList::factory()->create();

    foreach (range(1, 10) as $_) {
        $this->postJson('/api/lists/does-not-exist-000000/duplicate')->assertNotFound();
    }

    $this->postJson("/api/lists/{$list->slug}/duplicate")->assertStatus(429);
});

it('spends the writes allowance when resetting and restoring purchased items', function () {
    $list = ShoppingList::factory()->create();

    foreach (range(1, 60) as $_) {
        $this->postJson("/api/lists/{$list->slug}/items/reset-purchased")->assertOk();
        $this->postJson("/api/lists/{$list->slug}/items/restore-purchased", ['ids' => [1]])->assertOk();
    }

    $this->postJson("/api/lists/{$list->slug}/items/reset-purchased")
        ->assertStatus(429)
        ->assertHeader('Retry-After');

    $this->postJson("/api/lists/{$list->slug}/items/restore-purchased", ['ids' => [1]])->assertStatus(429);
});

it('does not let duplicating spend the writes allowance', function () {
    $list = ShoppingList::factory()->create();

    foreach (range(1, 10) as $_) {
        $this->postJson("/api/lists/{$list->slug}/duplicate")->assertCreated();
    }

    $this->patchJson("/api/lists/{$list->slug}", ['name' => 'Still allowed'])->assertOk();
});
