<?php

namespace App\Console\Commands;

use App\Models\ShoppingList;
use Illuminate\Console\Command;

/**
 * Lists without usage or modification activity during the configured
 * inactivity period are abandoned; this command physically deletes them with
 * their items. The Hostinger cron invokes it (constitution 5: the app keeps
 * no scheduler of its own).
 */
class PurgeInactiveLists extends Command
{
    protected $signature = 'lists:purge-inactive';

    protected $description = 'Borra físicamente las listas inactivas (sin uso ni modificación durante el periodo configurado)';

    public function handle(): int
    {
        $months = config('shopping_list.inactive_months');

        if (blank($months)) {
            $this->error('Indica SHOPPING_LIST_INACTIVE_MONTHS con el periodo en meses (p. ej. 12).');

            return self::FAILURE;
        }

        $cutoff = now()->subMonths((int) $months);
        $count = 0;

        ShoppingList::query()->inactiveSince($cutoff)->each(function (ShoppingList $list) use (&$count) {
            $list->deleteWithItems();
            $count++;
        });

        $this->info("Listas purgadas: {$count}");

        return self::SUCCESS;
    }
}
