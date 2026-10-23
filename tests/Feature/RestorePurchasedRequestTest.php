<?php

use App\Http\Requests\RestorePurchasedRequest;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::post('/_test/restore-purchased', fn (RestorePurchasedRequest $r) => $r->validated());
});

it('accepts a list of positive integer ids', function () {
    $this->postJson('/_test/restore-purchased', ['ids' => [1, 2, 30]])
        ->assertOk()
        ->assertExactJson(['ids' => [1, 2, 30]]);
});

it('accepts exactly 1000 ids', function () {
    $this->postJson('/_test/restore-purchased', ['ids' => range(1, 1000)])->assertOk();
});

it('rejects a missing ids field with a Spanish message', function () {
    $this->postJson('/_test/restore-purchased', [])
        ->assertStatus(422)
        ->assertJsonPath('errors.ids.0', 'El campo ids es obligatorio.');
});

it('rejects an empty ids list', function () {
    $this->postJson('/_test/restore-purchased', ['ids' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids');
});

it('rejects ids that is not an array', function () {
    $this->postJson('/_test/restore-purchased', ['ids' => 5])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids');
});

it('rejects more than 1000 ids', function () {
    $this->postJson('/_test/restore-purchased', ['ids' => range(1, 1001)])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids');
});

it('rejects non-integer ids', function (mixed $bad) {
    $this->postJson('/_test/restore-purchased', ['ids' => [1, $bad]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids.1');
})->with(['text' => 'abc', 'decimal' => 1.5, 'null' => null]);

it('rejects ids below 1', function (int $bad) {
    $this->postJson('/_test/restore-purchased', ['ids' => [$bad]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids.0');
})->with([0, -3]);

it('rejects repeated ids', function () {
    $this->postJson('/_test/restore-purchased', ['ids' => [4, 4]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids.0');
});
