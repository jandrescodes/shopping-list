<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| REST endpoints for shopping lists and items. No authentication: the
| unguessable list slug is the only access key (constitution 4). The
| concrete routes are added in task T21.
|
*/

use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ShoppingListController;
use Illuminate\Support\Facades\Route;

Route::post('/lists', [ShoppingListController::class, 'store']);
Route::get('/lists/{list}', [ShoppingListController::class, 'show']);
Route::patch('/lists/{list}', [ShoppingListController::class, 'update']);
Route::delete('/lists/{list}', [ShoppingListController::class, 'destroy']);

Route::get('/lists/{list}/items', [ItemController::class, 'sync']);
Route::post('/lists/{list}/items', [ItemController::class, 'store'])->scopeBindings();
Route::post('/lists/{list}/items/purge-purchased', [ItemController::class, 'purgePurchased']);
Route::patch('/lists/{list}/items/{item}', [ItemController::class, 'update'])->scopeBindings();
Route::delete('/lists/{list}/items/{item}', [ItemController::class, 'destroy'])->scopeBindings();
