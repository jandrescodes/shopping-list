<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;

it('renders a Spanish 404 page with a way back, identical for any missing slug', function () {
    $first = $this->get('/l/does-not-exist');
    $second = $this->get('/l/another-missing-slug');

    $first->assertNotFound();
    $first->assertSee('Página no encontrada');
    $first->assertSee('href="/"', false);
    expect($second->getContent())->toBe($first->getContent());
});

it('renders a Spanish 500 page with a retry link when debug is off', function () {
    config(['app.debug' => false]);
    Route::get('/boom', fn () => throw new RuntimeException('secret-detail-xyz'));

    $response = $this->get('/boom');

    $response->assertStatus(500);
    $response->assertSee('Algo salió mal');
    $response->assertSee('Reintentar');
    $response->assertDontSee('secret-detail-xyz');
});

it('renders the 500 page without build assets', function () {
    config(['app.debug' => false]);
    Vite::useManifestFilename('missing-manifest.json');
    Route::get('/boom', fn () => throw new RuntimeException('secret-detail-xyz'));

    $response = $this->get('/boom');

    $response->assertStatus(500);
    $response->assertSee('Algo salió mal');
    $response->assertDontSee('/build/', false);
});

it('renders a Spanish 503 page with a retry link', function () {
    Route::get('/maintenance', fn () => abort(503));

    $response = $this->get('/maintenance');

    $response->assertStatus(503);
    $response->assertSee('Estamos en mantenimiento');
    $response->assertSee('Reintentar');
});

it('keeps answering JSON for API errors', function () {
    $this->getJson('/api/lists/does-not-exist-000000')->assertNotFound()->assertExactJson(['message' => 'Not Found']);
});
