<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreListRequest;
use App\Http\Requests\UpdateListRequest;
use App\Http\Resources\ItemResource;
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
        $list = ShoppingList::create($request->validated());

        return response()->json([
            'slug' => $list->slug,
            'name' => $list->name,
            'url' => rtrim((string) config('app.url'), '/')."/l/{$list->slug}",
        ], 201);
    }

    /**
     * Show a list with its items, server-ordered: not purchased first, then
     * purchased; each group by creation date ascending.
     */
    public function show(ShoppingList $list): JsonResponse
    {
        return response()->json([
            'slug' => $list->slug,
            'name' => $list->name,
            'version' => $list->version,
            'items' => ItemResource::collection($list->activeItemsOrdered())->resolve(),
        ]);
    }

    /**
     * Rename a list, keeping its slug and bumping the version counter through
     * the locked versioned-write helper.
     */
    public function update(UpdateListRequest $request, ShoppingList $list): JsonResponse
    {
        ListVersion::write($list, function (ShoppingList $locked) use ($request) {
            $locked->fill($request->validated())->save();
        });

        return response()->json([
            'slug' => $list->slug,
            'name' => $list->name,
            'version' => $list->version,
        ]);
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
