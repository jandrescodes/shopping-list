<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Models\ShoppingList;
use App\Support\ListVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ItemController extends Controller
{
    /**
     * Add an item to a list in the "not purchased" state, through the locked
     * versioned-write helper: the list row is locked, the 200 active-item cap
     * is checked under that lock, and the new item is stamped with the bumped
     * version.
     */
    public function store(StoreItemRequest $request, ShoppingList $list): JsonResponse
    {
        $item = ListVersion::write($list, function (ShoppingList $locked) use ($request) {
            if ($locked->hasReachedActiveItemLimit()) {
                abort(422, 'La lista alcanzó el límite de 200 ítems.');
            }

            return $locked->items()->create($request->validated());
        });

        return response()->json((new ItemResource($item))->resolve(), 201);
    }

    /**
     * Edit an item field by field: only the fields present in the request are
     * written, so concurrent edits to different fields both survive. Marking an
     * item purchased/not purchased needs no confirmation step. The write goes
     * through the locked versioned-write helper.
     */
    public function update(UpdateItemRequest $request, ShoppingList $list, Item $item): JsonResponse
    {
        ListVersion::write($list, function () use ($request, $item) {
            $item->fill($request->validated())->save();

            return $item;
        });

        return response()->json((new ItemResource($item->fresh()))->resolve());
    }

    /**
     * Soft delete an item: the row is kept as a tombstone for sync, stamped
     * with the bumped version through the locked versioned-write helper. It
     * stops showing in the list right away.
     */
    public function destroy(ShoppingList $list, Item $item): Response
    {
        ListVersion::write($list, function () use ($item) {
            $item->delete();

            return $item;
        });

        return response()->noContent();
    }

    /**
     * Soft delete every item that is purchased in the database at the moment
     * this runs -- not what the client believed -- in a single pass through the
     * locked versioned-write helper. With nothing purchased it changes nothing
     * and leaves the list version untouched.
     */
    public function purgePurchased(ShoppingList $list): JsonResponse
    {
        if (! $list->hasPurchasedItems()) {
            return response()->json(['deleted_ids' => []]);
        }

        $purged = ListVersion::write($list, fn (ShoppingList $locked) => $locked->purgePurchasedItems());

        return response()->json(['deleted_ids' => $purged->pluck('id')->all()]);
    }

    /**
     * Incremental read for device sync. The cursor is the list version the
     * server handed back last time; the client re-sends it verbatim and never
     * interprets it. With a valid cursor (integer in 0..version) the response
     * is a delta: items changed since (version > cursor) plus the ids of items
     * tombstoned in that window. With the cursor missing, non-integer or past
     * the current version, the response is the full active state with an empty
     * deleted_ids. Either way `cursor` is the list's current version.
     */
    public function sync(Request $request, ShoppingList $list): JsonResponse
    {
        $version = $list->version;
        $cursor = $list->resolveSyncCursor($request->query('cursor'));

        if ($cursor === null) {
            return response()->json([
                'items' => ItemResource::collection($list->activeItemsOrdered())->resolve(),
                'deleted_ids' => [],
                'cursor' => $version,
            ]);
        }

        return response()->json([
            'items' => ItemResource::collection($list->activeItemsChangedSince($cursor))->resolve(),
            'deleted_ids' => $list->deletedItemIdsSince($cursor),
            'cursor' => $version,
        ]);
    }
}
