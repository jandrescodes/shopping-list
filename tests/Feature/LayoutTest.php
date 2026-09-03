<?php

it('renders the home page with the PWA layout', function () {
    $this->withoutVite();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('<link rel="manifest"', false);
    $response->assertSee('name="viewport"', false);
    $response->assertSee('name="theme-color"', false);
    $response->assertSee('serviceWorker.register', false);
});
