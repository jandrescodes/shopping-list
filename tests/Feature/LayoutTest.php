<?php

it('renders the home page with the PWA layout', function () {
    $this->withoutVite();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('<link rel="manifest"', false);
    $response->assertSee('name="viewport"', false);
    $response->assertSee('name="theme-color"', false);

    // Service worker registration lives in resources/js/app.js, loaded via
    // @vite (stripped by withoutVite() above), not inlined in the view.
    expect(file_get_contents(resource_path('js/app.js')))->toContain('serviceWorker.register');
});
