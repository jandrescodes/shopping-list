<?php

use App\Models\ShoppingList;
use Illuminate\Support\Facades\Route;

// Home page: create-list form plus the "my lists" section, populated client-side
// from localStorage (RF-6). The server never enumerates lists (RF-5).
Route::get('/', function () {
    return view('home');
});

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
