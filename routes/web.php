<?php

use App\Models\ShoppingList;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// List page. Exact slug match; direct 404 if it does not exist (plan). The
// full view is built in T26; the `noindex` middleware keeps the unguessable
// slug out of search indexes (RNF).
Route::get('/l/{list}', function (ShoppingList $list) {
    return response('<!DOCTYPE html><title>'.e($list->name).'</title>');
})->middleware('noindex');
