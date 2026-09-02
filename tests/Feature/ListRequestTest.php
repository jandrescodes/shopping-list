<?php

use App\Http\Requests\StoreListRequest;
use App\Http\Requests\UpdateListRequest;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::post('/_test/store-list', fn (StoreListRequest $r) => $r->validated());
    Route::post('/_test/update-list', fn (UpdateListRequest $r) => $r->validated());
});

it('rejects a blank name with a Spanish message', function () {
    $this->postJson('/_test/store-list', ['name' => ''])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre es obligatorio.');
});

it('rejects a whitespace-only name', function () {
    $this->postJson('/_test/store-list', ['name' => '     '])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre es obligatorio.');
});

it('rejects a name longer than 60 characters', function () {
    $this->postJson('/_test/store-list', ['name' => str_repeat('a', 61)])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre no debe tener más de 60 caracteres.');
});

it('accepts a valid name and trims surrounding spaces', function () {
    $this->postJson('/_test/store-list', ['name' => '  Feria  '])
        ->assertOk()
        ->assertJson(['name' => 'Feria']);
});

it('applies the same rules when renaming a list', function () {
    $this->postJson('/_test/update-list', ['name' => ''])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre es obligatorio.');

    $this->postJson('/_test/update-list', ['name' => 'Despensa'])
        ->assertOk()
        ->assertJson(['name' => 'Despensa']);
});
