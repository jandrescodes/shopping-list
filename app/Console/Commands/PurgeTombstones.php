<?php

namespace App\Console\Commands;

use App\Models\Item;
use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Console\Command;

/**
 * Purga manual de lápidas de sincronización (RF-16). Los ítems con borrado
 * lógico se conservan como tombstone para que `sync` los propague en el delta;
 * no se purgan de forma automática (constitución 5: sin scheduler/cron). Este
 * comando se corre a mano en mantenimiento cuando ya ningún cliente necesita
 * ese delta.
 */
class PurgeTombstones extends Command
{
    protected $signature = 'items:purge-tombstones {--before= : Fecha límite; se borran las lápidas con deleted_at anterior}';

    protected $description = 'Borra físicamente los ítems con borrado lógico anteriores a una fecha (RF-16)';

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
