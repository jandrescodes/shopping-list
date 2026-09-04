<?php

use App\Models\ShoppingList;
use Illuminate\Support\Facades\Route;

// Home page: create-list form plus the "my lists" section, populated client-side
// from localStorage. The server never enumerates lists.
Route::get('/', function () {
    return view('home');
});

// PWA manifest and icons. On shared hosting Apache serves these files from
// public/ directly; these routes are the fallback that also lets the test suite
// and `artisan serve` reach them.
Route::get('/manifest.json', fn () => response()->file(public_path('manifest.json'), [
    'Content-Type' => 'application/manifest+json',
]));

Route::get('/icons/{icon}', function (string $icon) {
    abort_unless(in_array($icon, ['icon-32.png', 'icon-192.png', 'icon-512.png'], true), 404);

    return response()->file(public_path("icons/{$icon}"), ['Content-Type' => 'image/png']);
});

// Service worker (app shell / offline). Same as the manifest: in production
// Apache serves it directly; this route is the fallback for tests and
// `artisan serve`. Served from the root so its scope covers the app.
Route::get('/sw.js', fn () => response()->file(public_path('sw.js'), [
    'Content-Type' => 'text/javascript',
    'Service-Worker-Allowed' => '/',
]));

// Minimal static offline fallback served from the service worker cache when the
// app is opened with no network and was never cached before.
Route::get('/offline', fn () => view('offline'));

// List page. Exact slug match; direct 404 if it does not exist. Items are
// rendered server-side in the same order the client uses (not purchased first,
// then purchased; each group by creation ascending); resources/js/list.js
// takes over from there. The `noindex` middleware keeps the unguessable slug
// out of search indexes.
Route::get('/l/{list}', function (ShoppingList $list) {
    $items = $list->items()
        ->orderBy('is_purchased')
        ->orderBy('created_at')
        ->orderBy('id')
        ->get();

    return view('list', ['list' => $list, 'items' => $items]);
})->middleware('noindex');
