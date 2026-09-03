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
    $response->assertSee('myShoppingLists', false);
});
