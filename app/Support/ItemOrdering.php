<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Orden manual de los ítems de una lista como una sola secuencia con huecos de
 * STEP. No toca la base de datos: recibe los demás ítems ya cargados y devuelve
 * las posiciones nuevas, para que el modelo solo lea y persista.
 */
final class ItemOrdering
{
    public const STEP = 1024;

    /**
     * Posiciones que deja mover un ítem justo antes de `$beforeId` (o al final
     * si es null, desconocido o ajeno a la lista).
     *
     * @param  list<array{id: int, position: int}>  $others  resto de ítems activos, ordenados por (position, id)
     * @return array<int, int> id => posición nueva: vacío si ya está en su sitio,
     *                         una entrada si basta el punto medio, todos los ítems si hubo que renumerar
     */
    public static function move(array $others, int $movingId, int $movingPosition, ?int $beforeId): array
    {
        if ($beforeId === $movingId) {
            return [];
        }

        $slot = self::slotOf($others, $beforeId);

        if (self::isInPlace($others, $movingId, $movingPosition, $slot)) {
            return [];
        }

        $position = self::positionAt($others, $slot);

        return $position === null
            ? self::renumber($others, $movingId, $slot)
            : [$movingId => $position];
    }

    /**
     * @param  list<array{id: int, position: int}>  $others
     */
    private static function slotOf(array $others, ?int $beforeId): ?int
    {
        if ($beforeId === null) {
            return null;
        }

        foreach ($others as $index => $other) {
            if ($other['id'] === $beforeId) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Null cuando el hueco antes del vecino se agotó y hay que renumerar.
     *
     * @param  list<array{id: int, position: int}>  $others
     */
    private static function positionAt(array $others, ?int $slot): ?int
    {
        if ($slot === null) {
            $last = $others === [] ? 0 : $others[array_key_last($others)]['position'];

            return $last + self::STEP;
        }

        $before = $others[$slot]['position'];

        if ($slot === 0) {
            return $before - self::STEP;
        }

        $previous = $others[$slot - 1]['position'];
        $position = ($previous + $before) >> 1;

        return $position > $previous && $position < $before ? $position : null;
    }

    /**
     * @param  list<array{id: int, position: int}>  $others
     * @return array<int, int>
     */
    private static function renumber(array $others, int $movingId, ?int $slot): array
    {
        $ids = array_column($others, 'id');
        array_splice($ids, $slot ?? count($ids), 0, [$movingId]);

        $positions = [];
        foreach ($ids as $index => $id) {
            $positions[$id] = ($index + 1) * self::STEP;
        }

        return $positions;
    }

    /**
     * @param  list<array{id: int, position: int}>  $others
     */
    private static function isInPlace(array $others, int $movingId, int $movingPosition, ?int $slot): bool
    {
        $previous = $slot === null
            ? ($others === [] ? null : $others[array_key_last($others)])
            : ($slot > 0 ? $others[$slot - 1] : null);
        $next = $slot === null ? null : $others[$slot];

        $compare = fn (array $other): int => [$other['position'], $other['id']] <=> [$movingPosition, $movingId];

        return ($previous === null || $compare($previous) < 0)
            && ($next === null || $compare($next) > 0);
    }
}
