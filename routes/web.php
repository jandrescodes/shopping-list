<?php

use App\Models\ShoppingList;
use Illuminate\Support\Facades\Route;

// Home page: create-list form plus the "my lists" section, populated client-side
// from localStorage (RF-6). The server never enumerates lists (RF-5).
Route::get('/', function () {
    return view('home');
});

// PWA manifest and icons. On shared hosting Apache serves these files from
// public/ directly; these routes are the fallback that also lets the test suite
// and `artisan serve` reach them (RF-28).
Route::get('/manifest.json', fn () => response()->file(public_path('manifest.json'), [
    'Content-Type' => 'application/manifest+json',
]));

Route::get('/icons/{icon}', function (string $icon) {
    abort_unless(in_array($icon, ['icon-32.png', 'icon-192.png', 'icon-512.png'], true), 404);

    return response()->file(public_path("icons/{$icon}"), ['Content-Type' => 'image/png']);
});

// Service worker (app shell / offline, RF-26 y RF-29). Igual que el manifest:
// en producción lo sirve Apache; la ruta es el fallback para tests y
// `artisan serve`. Se sirve desde la raíz para que el scope cubra toda la app.
Route::get('/sw.js', fn () => response()->file(public_path('sw.js'), [
    'Content-Type' => 'text/javascript',
    'Service-Worker-Allowed' => '/',
]));

// Minimal static offline fallback served from the service worker cache when the
// app is opened with no network and was never cached before (RF-29).
Route::get('/offline', fn () => view('offline'));

// List page. Exact slug match; direct 404 if it does not exist (plan). Items are
// rendered server-side in the RF-18 order (not purchased first, then purchased;
// each group by creation ascending); resources/js/list.js takes over from there.
// The `noindex` middleware keeps the unguessable slug out of search indexes (RNF).
Route::get('/l/{list}', function (ShoppingList $list) {
    $items = $list->items()
        ->orderBy('is_purchased')
        ->orderBy('created_at')
        ->orderBy('id')
        ->get();

    return view('list', ['list' => $list, 'items' => $items]);
})->middleware('noindex');
