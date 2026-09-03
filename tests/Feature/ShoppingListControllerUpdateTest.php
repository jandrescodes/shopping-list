<?php

use App\Models\ShoppingList;

it('renames a list, keeps the slug and bumps the version', function () {
    $list = ShoppingList::factory()->create(['name' => 'Feria']);
    $slug = $list->slug;

    $this->patchJson("/api/lists/{$slug}", ['name' => 'Despensa'])
        ->assertOk()
        ->assertJson(['slug' => $slug, 'name' => 'Despensa', 'version' => 1]);

    expect($list->fresh()->name)->toBe('Despensa')
        ->and($list->fresh()->slug)->toBe($slug);
});

it('applies the last write on two sequential renames and bumps version twice', function () {
    $list = ShoppingList::factory()->create(['name' => 'Feria']);

    $this->patchJson("/api/lists/{$list->slug}", ['name' => 'Uno'])->assertOk();
    $this->patchJson("/api/lists/{$list->slug}", ['name' => 'Dos'])
        ->assertOk()
        ->assertJsonPath('version', 2);

    expect($list->fresh()->name)->toBe('Dos');
});

it('returns 404 when renaming an unknown list', function () {
    $this->patchJson('/api/lists/does-not-exist-000000', ['name' => 'X'])->assertNotFound();
});

it('rejects an invalid name', function () {
    $list = ShoppingList::factory()->create();

    $this->patchJson("/api/lists/{$list->slug}", ['name' => ''])->assertStatus(422);
});
