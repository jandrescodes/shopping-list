<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreListRequest;
use App\Http\Requests\UpdateListRequest;
use App\Http\Resources\ItemResource;
use App\Http\Resources\ShoppingListResource;
use App\Models\ShoppingList;
use App\Support\ListVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ShoppingListController extends Controller
{
    /**
     * Create a list and return its public absolute link.
     */
    public function store(StoreListRequest $request): JsonResponse
    {
        return $this->created(ShoppingList::create($request->validated()));
    }

    /**
     * Copy a list (name, currency and unmarked items) into a new list with its
     * own slug. It spends the same `lists-create` allowance as creating one.
     */
    public function duplicate(ShoppingList $list): JsonResponse
    {
        return $this->created($list->duplicate());
    }

    /**
     * Show a list with its items, server-ordered: not purchased first, then
     * purchased; each group by creation date ascending.
     */
    public function show(ShoppingList $list): JsonResponse
    {
        return response()->json(array_merge(
            ShoppingListResource::make($list)->resolve(),
            ['items' => ItemResource::collection($list->activeItemsOrdered())->resolve()],
        ));
    }

    private function created(ShoppingList $list): JsonResponse
    {
        return response()->json([
            'slug' => $list->slug,
            'name' => $list->name,
            'url' => rtrim((string) config('app.url'), '/')."/l/{$list->slug}",
        ], 201, ['Location' => url("/api/lists/{$list->slug}")]);
    }

    /**
     * Rename a list or change its currency, keeping its slug and bumping the
     * version counter through the locked versioned-write helper.
     */
    public function update(UpdateListRequest $request, ShoppingList $list): JsonResponse
    {
        ListVersion::write($list, function (ShoppingList $locked) use ($request) {
            $locked->fill($request->validated())->save();
        });

        return response()->json(ShoppingListResource::make($list)->resolve());
    }

    /**
     * Physically delete a list and every one of its items, active rows and
     * tombstones alike. Any later access to the slug is a plain 404, byte for
     * byte identical to a slug that never existed.
     */
    public function destroy(ShoppingList $list): Response
    {
        $list->deleteWithItems();

        return response()->noContent();
    }
}
