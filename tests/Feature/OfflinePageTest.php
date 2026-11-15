<?php

it('serves a minimal offline page (RF-29)', function () {
    $response = $this->get('/offline');

    $response->assertOk();
    $response->assertSee('Sin conexión');
    $response->assertSee('Reintentar');
    // Static fallback: no build assets, no service worker registration.
    $response->assertDontSee('@vite', false);
    $response->assertDontSee('serviceWorker.register', false);
    // Follows the system theme with its own inline styles.
    $response->assertSee('<meta name="color-scheme" content="light dark">', false);
    $response->assertSee('<meta name="theme-color" content="#2563eb" media="(prefers-color-scheme: light)">', false);
    $response->assertSee('<meta name="theme-color" content="#030712" media="(prefers-color-scheme: dark)">', false);
    $response->assertSee('color-scheme: light dark;', false);
    $response->assertSee('@media (prefers-color-scheme: dark)', false);
});
