<?php

namespace App\Support;

use App\Models\Item;
use App\Models\ShoppingList;
use Closure;
use Illuminate\Support\Facades\DB;

class ListVersion
{
    /**
     * Run a write against a list inside a transaction with the list row locked,
     * bump the list version counter atomically and stamp that new version on
     * every Item the callback reports as touched (active rows and tombstones).
     *
     * The callback receives the locked list and the new version number, and
     * returns the touched item(s): an Item, an iterable of Items, or null.
     *
     * @param  Closure(ShoppingList, int): mixed  $callback
     */
    public static function write(ShoppingList $list, Closure $callback): mixed
    {
        return DB::transaction(function () use ($list, $callback) {
            $locked = ShoppingList::whereKey($list->getKey())->lockForUpdate()->firstOrFail();

            $version = $locked->bumpVersion();

            $result = $callback($locked, $version);

            foreach (self::touchedItems($result) as $item) {
                $item->version = $version;
                $item->save();
            }

            $list->setRawAttributes($locked->getAttributes(), true);

            return $result;
        });
    }

    /**
     * @return iterable<Item>
     */
    private static function touchedItems(mixed $result): iterable
    {
        if ($result instanceof Item) {
            return [$result];
        }

        if (is_iterable($result)) {
            return collect($result)->filter(fn ($item) => $item instanceof Item);
        }

        return [];
    }
}
