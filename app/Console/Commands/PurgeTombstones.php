<?php

namespace App\Console\Commands;

use App\Models\Item;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;

/**
 * Soft-deleted items are kept as tombstones so `sync` can propagate them in
 * the delta; they are never purged automatically (constitution 5: no
 * scheduler/cron). This command is run by hand during maintenance once no
 * client still needs that delta.
 */
class PurgeTombstones extends Command
{
    protected $signature = 'items:purge-tombstones {--before= : Fecha límite; se borran las lápidas con deleted_at anterior}';

    protected $description = 'Borra físicamente los ítems con borrado lógico anteriores a una fecha';

    public function handle(): int
    {
        $before = $this->option('before');

        if (blank($before)) {
            $this->error('Indica --before=<fecha> con la fecha límite (p. ej. --before=2026-01-01).');

            return self::FAILURE;
        }

        try {
            $cutoff = Carbon::parse($before);
        } catch (InvalidFormatException) {
            $this->error("Fecha no válida: {$before}");

            return self::FAILURE;
        }

        $count = Item::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();

        $this->info("Lápidas purgadas: {$count} (anteriores a {$cutoff->toDateTimeString()}).");

        return self::SUCCESS;
    }
}
