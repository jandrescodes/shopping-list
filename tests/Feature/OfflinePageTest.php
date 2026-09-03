<?php

it('serves a minimal offline page (RF-29)', function () {
    $response = $this->get('/offline');

    $response->assertOk();
    $response->assertSee('Sin conexión');
    // Static fallback: no build assets, no service worker registration.
    $response->assertDontSee('@vite', false);
    $response->assertDontSee('serviceWorker.register', false);
});
