<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| REST endpoints for shopping lists and items. No authentication: the
| unguessable list slug is the only access key (constitution 4). There is
| deliberately no route that enumerates lists. Nested item routes use scoped
| bindings so an item is always resolved within its list's scope.
|
| Per-IP throttles (defined in bootstrap/app.php): `lists-create` on list
| creation, `writes` on every mutation, `sync` on the polling GET. Reads of a
| single list are not throttled.
|
*/

use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ShoppingListController;
use Illuminate\Support\Facades\Route;

Route::post('/lists', [ShoppingListController::class, 'store'])->middleware('throttle:lists-create');
Route::get('/lists/{list}', [ShoppingListController::class, 'show']);
Route::patch('/lists/{list}', [ShoppingListController::class, 'update'])->middleware('throttle:writes');
Route::delete('/lists/{list}', [ShoppingListController::class, 'destroy'])->middleware('throttle:writes');

Route::prefix('/lists/{list}/items')->scopeBindings()->group(function () {
    Route::get('/', [ItemController::class, 'sync'])->middleware('throttle:sync');

    Route::middleware('throttle:writes')->group(function () {
        Route::post('/', [ItemController::class, 'store']);
        Route::post('/purge-purchased', [ItemController::class, 'purgePurchased']);
        Route::patch('/{item}', [ItemController::class, 'update']);
        Route::delete('/{item}', [ItemController::class, 'destroy']);
    });
});
