# Validación — Spec 006

**Date:** 2026-11-01

Fase 7 del flujo SDD, generada por `/sdd:validate`.

## Verificación ejecutada

| Comprobación             | Resultado                         |
| ------------------------ | --------------------------------- |
| `php artisan test`       | PASS — 274 tests, 1453 assertions |
| `vendor/bin/pint --test` | PASS                              |

No aplica `npx playwright test`: la feature no toca `resources/js/`
(constitución 3 y `AGENTS.md` solo lo exigen para la capa de cliente).

## Recorrido RF por RF

| RF   | Evidencia                                                                                                                                                                                                                                                               | Veredicto |
| ---- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------- |
| RF-1 | `PurgeInactiveListsCommandTest`: purga la lista con `updated_at` de hace 2 meses cuando el periodo es 1; la lista con actividad reciente sobrevive.                                                                                                                     | PASS      |
| RF-2 | `PurgeInactiveListsCommandTest`: un `PATCH` real de ítem renueva la actividad y la lista envejecida no se purga. Mecanismo verificado en el vendor: `ListVersion::write` → `bumpVersion()` → `Eloquent\Builder::increment` renueva `updated_at` (`addUpdatedAtColumn`). | PASS      |
| RF-3 | `PurgeInactiveListsCommandTest`: tras la purga no queda ningún ítem ni con `Item::withTrashed()` (activos y lápidas) y la fila de la lista desaparece.                                                                                                                  | PASS      |
| RF-4 | `PurgeInactiveListsCommandTest`: reporta `Listas purgadas: 2` y, sin abandonadas, `Listas purgadas: 0` con exit 0.                                                                                                                                                      | PASS      |
| RF-5 | `PurgeInactiveListsCommandTest`: con `shopping_list.inactive_months` en 12 el comando no aborta (lee el periodo de la configuración).                                                                                                                                   | PASS      |
| RF-6 | `PurgeInactiveListsCommandTest`: con el periodo nulo sale con código 1 y el mensaje que apunta a `SHOPPING_LIST_INACTIVE_MONTHS`.                                                                                                                                       | PASS      |

## Requisitos no funcionales

| RNF                                         | Evidencia                                                                                | Veredicto |
| ------------------------------------------- | ---------------------------------------------------------------------------------------- | --------- |
| Sin cambios en el contrato (constitución 5) | Diff sin rutas ni controladores nuevos; la suite completa queda en verde.                | PASS      |
| Sin scheduler en la app (constitución 5)    | No hay registro de scheduler; el comando lo invoca el cron de hPanel (uid `fGLEKPsN2B`). | PASS      |
| Borrado físico sin soft deletes nuevos      | `deleteWithItems()` fuerza el borrado de ítems y lista; sin migraciones nuevas.          | PASS      |

## Uncovered FRs

- Ninguno: RF-1 a RF-6 tienen evidencia automatizada.

## Criterios de finalización

- [x] RF-1 a RF-6 verificados con tests Pest del comando `lists:purge-inactive` — 7 tests en `tests/Feature/PurgeInactiveListsCommandTest.php`.
- [x] El purge no toca listas activas — `it does not purge a list with recent activity` y la lista reciente del test de conteo.

## Veredicto

**Spec fulfilled (spec cumplida).** RF-1 a RF-6 en verde, criterios de
finalización cumplidos y sin cambios de contrato ni de esquema.

## Updated files

- `docs/roadmap.md` — la entrada "Limpieza de listas abandonadas" pasó de
  "Backlog / ideas 💡" a "Hecho ✅" con el enlace a la carpeta de la spec.
- `docs/schema.md` — sin cambios (modo automático del procedimiento `schema`:
  las fuentes del documento siguen siendo sus 7 migraciones y la feature no
  altera el esquema).

## Known limits

- La limpieza es periódica: el cron de hPanel corre a las 03:00 (uid
  `fGLEKPsN2B`), así que una lista abandonada permanece hasta la siguiente
  ejecución.
- Una lista purgada deja de existir para todos los dispositivos: su slug
  responde 404 y no hay recuperación ni aviso previo a los miembros del hogar.

## Notes

- Mecanismo de RF-2 verificado en el vendor durante la fase 6: la renovación
  no depende de `$touches` (Item no lo declara) sino de `bumpVersion()` en
  cada write, que renueva `updated_at` vía `addUpdatedAtColumn`.
