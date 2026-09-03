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
