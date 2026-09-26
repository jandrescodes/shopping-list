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

it('canonicalizes every accepted price shape', function () {
    $cases = [
        ['12.50', '12.50'],
        ['12,5', '12.5'],
        ['12', '12'],
        ['0', '0'],
        [' 12,50 ', '12.50'],
    ];

    foreach ($cases as [$input, $canonical]) {
        $this->postJson('/_test/store-item', ['name' => 'Leche', 'price' => $input])
            ->assertOk()
            ->assertJsonPath('price', $canonical);
    }
});

it('turns an empty or separator-only price into null', function () {
    foreach (['', '   ', ',', '.', ' ,. '] as $input) {
        $this->postJson('/_test/store-item', ['name' => 'Leche', 'price' => $input])
            ->assertOk()
            ->assertJsonPath('price', null);
    }
});

it('rejects invalid prices with a Spanish message', function () {
    foreach (['-1', 'abc', '1.999', '1.234,50', '999999999.99'] as $price) {
        $this->postJson('/_test/store-item', ['name' => 'Leche', 'price' => $price])
            ->assertStatus(422)
            ->assertJsonPath(
                'errors.price.0',
                'El precio debe ser un número de hasta 8 dígitos y 2 decimales, por ejemplo 12,50.'
            );
    }
});

it('answers a rejected price with no raw placeholder in the body', function () {
    $response = $this->postJson('/_test/store-item', ['name' => 'Leche', 'price' => '1.999'])
        ->assertStatus(422)
        ->assertJsonPath(
            'message',
            'El precio debe ser un número de hasta 8 dígitos y 2 decimales, por ejemplo 12,50.'
        );

    expect($response->getContent())
        ->not->toContain(':decimal')
        ->not->toContain(':attribute');
});
