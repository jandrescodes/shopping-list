<?php

it('serves the web app manifest with the required keys', function () {
    $response = $this->get('/manifest.json')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('manifest+json');

    $manifest = json_decode($response->streamedContent(), true);

    expect($manifest)
        ->toHaveKeys(['name', 'short_name', 'theme_color', 'background_color', 'display', 'start_url', 'icons'])
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['start_url'])->toBe('/');

    $sizes = collect($manifest['icons'])->pluck('sizes');
    expect($sizes)->toContain('192x192')->toContain('512x512');
});

it('serves the PWA icons as PNG', function () {
    foreach (['192', '512'] as $size) {
        $response = $this->get("/icons/icon-{$size}.png")->assertOk();
        expect($response->headers->get('Content-Type'))->toBe('image/png');
    }
});
