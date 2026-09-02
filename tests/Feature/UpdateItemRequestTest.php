<?php

use App\Http\Requests\UpdateItemRequest;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::patch('/_test/update-item', fn (UpdateItemRequest $r) => $r->validated());
});

it('accepts a lone is_purchased toggle', function () {
    $this->patchJson('/_test/update-item', ['is_purchased' => true])
        ->assertOk()
        ->assertExactJson(['is_purchased' => true]);
});

it('rejects an empty name when the field is present', function () {
    $this->patchJson('/_test/update-item', ['name' => ''])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre es obligatorio.');
});

it('rejects a name longer than 100 characters', function () {
    $this->patchJson('/_test/update-item', ['name' => str_repeat('a', 101)])
        ->assertStatus(422)
        ->assertJsonPath('errors.name.0', 'El campo nombre no debe tener más de 100 caracteres.');
});

it('rejects quantity longer than 50 characters', function () {
    $this->patchJson('/_test/update-item', ['quantity' => str_repeat('x', 51)])
        ->assertStatus(422)
        ->assertJsonPath('errors.quantity.0', 'El campo cantidad no debe tener más de 50 caracteres.');
});

it('rejects a non-boolean is_purchased', function () {
    $this->patchJson('/_test/update-item', ['is_purchased' => 'maybe'])
        ->assertStatus(422)
        ->assertJsonPath('errors.is_purchased.0', 'El campo estado de comprado debe ser verdadero o falso.');
});

it('trims a present name and blanks whitespace-only quantity to null', function () {
    $this->patchJson('/_test/update-item', ['name' => '  Pan  ', 'quantity' => '  '])
        ->assertOk()
        ->assertExactJson(['name' => 'Pan', 'quantity' => null]);
});

it('validates only the fields present in the request', function () {
    $this->patchJson('/_test/update-item', [])
        ->assertOk()
        ->assertExactJson([]);
});
