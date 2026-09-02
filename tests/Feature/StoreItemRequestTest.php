<?php

use App\Http\Requests\StoreItemRequest;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::post('/_test/store-item', fn (StoreItemRequest $r) => $r->validated());
});

it('rejects a blank item name with a Spanish message', function () {
    $this->postJson('/_test/store-item', ['name' => '   '])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre es obligatorio.');
});

it('rejects an item name longer than 100 characters', function () {
    $this->postJson('/_test/store-item', ['name' => str_repeat('a', 101)])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre no debe tener más de 100 caracteres.');
});

it('rejects quantity longer than 50 characters', function () {
    $this->postJson('/_test/store-item', ['name' => 'Leche', 'quantity' => str_repeat('x', 51)])
        ->assertStatus(422)
        ->assertJsonPath('errors.quantity.0', 'El campo cantidad no debe tener más de 50 caracteres.');
});

it('rejects added_by longer than 50 characters', function () {
    $this->postJson('/_test/store-item', ['name' => 'Leche', 'added_by' => str_repeat('x', 51)])
        ->assertStatus(422)
        ->assertJsonPath('errors.added_by.0', 'El campo quién lo agrega no debe tener más de 50 caracteres.');
});

it('turns whitespace-only quantity and added_by into null', function () {
    $this->postJson('/_test/store-item', [
        'name' => '  Leche  ',
        'quantity' => '   ',
        'added_by' => '  ',
    ])
        ->assertOk()
        ->assertExactJson(['name' => 'Leche', 'quantity' => null, 'added_by' => null]);
});

it('keeps trimmed quantity and added_by when present', function () {
    $this->postJson('/_test/store-item', [
        'name' => 'Leche',
        'quantity' => '  2 L ',
        'added_by' => ' Ana ',
    ])
        ->assertOk()
        ->assertExactJson(['name' => 'Leche', 'quantity' => '2 L', 'added_by' => 'Ana']);
});
