<?php

use App\Models\ShoppingList;

it('creates a list and returns slug, name and absolute url', function () {
    config(['app.url' => 'https://compras.example']);

    $response = $this->postJson('/api/lists', ['name' => '  Feria  ']);

    $response->assertCreated()
        ->assertJsonPath('name', 'Feria');

    $slug = $response->json('slug');
    expect($slug)->toMatch('/^[A-Za-z0-9_-]{22}$/');
    expect($response->json('url'))->toBe("https://compras.example/l/{$slug}");

    $this->assertDatabaseHas('shopping_lists', ['slug' => $slug, 'name' => 'Feria']);
});

it('rejects an invalid list name with 422', function () {
    $this->postJson('/api/lists', ['name' => ''])->assertStatus(422);
    $this->postJson('/api/lists', ['name' => str_repeat('a', 61)])->assertStatus(422);

    expect(ShoppingList::count())->toBe(0);
});

it('answers 422 as JSON even without an Accept header', function () {
    $this->post('/api/lists', ['name' => ''], ['Accept' => '*/*'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('points Location at the created list resource', function () {
    $response = $this->postJson('/api/lists', ['name' => 'Feria'])->assertCreated();

    $response->assertHeader('Location', url('/api/lists/'.$response->json('slug')));
});
