<?php

it('shows the create-list form on the home page', function () {
    $this->withoutVite();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('<form', false);
    $response->assertSee('id="list-name"', false);
    $response->assertSee('name="name"', false);
    $response->assertSee('Crear', false);
});

it('renders the "my lists" section without querying any list on the server', function () {
    $this->withoutVite();

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Mis listas', false);
    $response->assertSee('x-data="myLists()"', false);

    // The `myShoppingLists` read/write logic lives in resources/js/home.js,
    // loaded via @vite (stripped by withoutVite() above), not inlined in the view.
    expect(file_get_contents(resource_path('js/home.js')))->toContain('myShoppingLists');
});
