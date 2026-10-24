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

it('accepts a valid budget, zero included, and a decimal comma', function (string $sent, string $stored) {
    $this->postJson('/_test/update-list', ['budget' => $sent])
        ->assertOk()
        ->assertExactJson(['budget' => $stored]);
})->with([
    'integer' => ['150', '150'],
    'zero' => ['0', '0'],
    'comma' => ['150,50', '150.50'],
    'padded' => ['  12.5 ', '12.5'],
]);

it('clears the budget with null or a blank value', function (mixed $sent) {
    $this->postJson('/_test/update-list', ['budget' => $sent])
        ->assertOk()
        ->assertExactJson(['budget' => null]);
})->with([null, '', '   ', ',']);

it('leaves the budget out of validated when it is absent', function () {
    $this->postJson('/_test/update-list', ['currency' => 'USD'])
        ->assertOk()
        ->assertExactJson(['currency' => 'USD']);
});

it('rejects an invalid budget with a Spanish message', function (mixed $sent) {
    $this->postJson('/_test/update-list', ['budget' => $sent])
        ->assertStatus(422)
        ->assertJsonPath('errors.budget.0', 'El presupuesto debe ser un número de hasta 8 dígitos y 2 decimales, por ejemplo 150,50.');
})->with(['-5', 'abc', '1.999', '123456789', '1e3']);
