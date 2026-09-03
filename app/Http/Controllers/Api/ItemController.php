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
use Illuminate\Support\Collection;

class ItemController extends Controller
{
    /**
     * Add an item to a list in the "not purchased" state, through the locked
     * versioned-write helper: the list row is locked, the 200 active-item cap
     * is checked under that lock, and the new item is stamped with the bumped
     * version (RF-10, RF-11, RF-12, RF-13, RF-17, RF-20, RF-32).
     */
    public function store(StoreItemRequest $request, ShoppingList $list): JsonResponse
    {
        $item = ListVersion::write($list, function (ShoppingList $locked) use ($request) {
            if ($locked->items()->count() >= 200) {
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
     * through the locked versioned-write helper (RF-14, RF-15, RF-25).
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
     * stops showing in the list right away (RF-16).
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
     * and leaves the list version untouched (RF-19).
     */
    public function purgePurchased(ShoppingList $list): JsonResponse
    {
        if ($list->items()->where('is_purchased', true)->doesntExist()) {
            return response()->json(['deleted_ids' => []]);
        }

        $purged = ListVersion::write($list, function (ShoppingList $locked) {
            $purchased = $locked->items()->where('is_purchased', true)->get();
            $purchased->each->delete();

            return $purchased;
        });

        return response()->json(['deleted_ids' => $purged->pluck('id')->all()]);
    }

    /**
     * Incremental read for device sync. The cursor is the list version the
     * server handed back last time; the client re-sends it verbatim and never
     * interprets it. With a valid cursor (integer in 0..version) the response
     * is a delta: items changed since (version > cursor) plus the ids of items
     * tombstoned in that window. With the cursor missing, non-integer or past
     * the current version, the response is the full active state with an empty
     * deleted_ids. Either way `cursor` is the list's current version
     * (RF-18, RF-22, RF-24, RF-27).
     */
    public function sync(Request $request, ShoppingList $list): JsonResponse
    {
        $version = $list->version;
        $raw = $request->query('cursor');
        $cursor = is_string($raw) && ctype_digit($raw) ? (int) $raw : null;

        if ($cursor === null || $cursor > $version) {
            return response()->json([
                'items' => ItemResource::collection($this->orderedActiveItems($list))->resolve(),
                'deleted_ids' => [],
                'cursor' => $version,
            ]);
        }

        $items = $this->orderedActiveItems($list)->where('version', '>', $cursor)->values();

        $deletedIds = $list->items()->onlyTrashed()
            ->where('version', '>', $cursor)
            ->pluck('id')
            ->all();

        return response()->json([
            'items' => ItemResource::collection($items)->resolve(),
            'deleted_ids' => $deletedIds,
            'cursor' => $version,
        ]);
    }

    /**
     * Active items in the server-fixed order: not purchased first, then
     * purchased; each group by creation date ascending (RF-18).
     *
     * @return Collection<int, Item>
     */
    private function orderedActiveItems(ShoppingList $list): Collection
    {
        return $list->items()
            ->orderBy('is_purchased')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }
}
