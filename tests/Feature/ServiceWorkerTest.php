<?php

it('serves the service worker with a JavaScript mime type', function () {
    $response = $this->get('/sw.js')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('javascript');
});

it('precaches the offline page and the compiled asset manifest', function () {
    $body = $this->get('/sw.js')->streamedContent();

    expect($body)
        ->toContain("'/offline'")
        ->toContain('/build/manifest.json')
        ->toContain("url.pathname.startsWith('/api/')");
});

it('serves the offline fallback page', function () {
    $this->get('/offline')
        ->assertOk()
        ->assertSee('Sin conexión');
});
