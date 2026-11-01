# Plan técnico — Spec 006

## Estado de partida del repo

Release 1.5.0 (spec 005) cerrada. Baseline esperada: conteos de la spec 005
en verde (267 Pest, 118 Playwright). Contexto que condiciona el plan:

- `app/Console/Commands/PurgeTombstones.php` define el patrón de comando de
  mantenimiento: signature con `--before=`, aborta si falta/fecha inválida,
  `forceDelete` con cutoff, reporta conteo, exit 0. El comando del spec 006
  replica la shape con inactividad (RF-1..RF-6).
- `ShoppingList::deleteWithItems()` forceDelete items (conTrashed) + lista;
  `ShoppingList` no usa SoftDeletes (RF-3).
- Laravel renueva el `updated_at` del padre (`finishSave` → `touchOwners`) al
  guardar un ítem vía relación, así que `shopping_lists.updated_at` refleja la
  última modificación (RF-2).
- Cron verificado el 2026-11-01 (`hosting_cron-jobs_create`, uid `fGLEKPsN2B,
`0 3 \* \* \*`) → el plan lo permite (P7); el job ejecuta el artisan del sitio.

## Estructura de módulos

- `app/Console/Commands/PurgeInactiveLists.php` → comando `lists:purge-inactive` (RF-1, RF-3, RF-4, RF-5, RF-6).
- `app/Models/ShoppingList.php` → selección de listas inactivas (RF-1, RF-2, RF-5, RF-6).

## Modelo de datos

Sin cambios de schema. La inactividad se deriva de `shopping_lists.updated_at`
frente al periodo configurado (RF-1, RF-6); stateless en cuanto a datos nuevos.

## Algoritmos no triviales

### Purga de listas inactivas (RF-1, RF-3, RF-4, RF-5, RF-6)

1. Resolver periodo desde configuración (RF-5); abortar con error si no está (RF-6).
2. Seleccionar listas inactivas (sin actividad en el periodo).
3. Borrar físicamente (forceDelete lista + ítems) vía `deleteWithItems()` (RF-3).
4. Reportar conteo; exit 0 (RF-4).

## Decisiones técnicas

| #   | Decisión                                                               | Alternativa descartada y por qué                                                       |
| --- | ---------------------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| D1  | Periodo desde variable de entorno (decisión del usuario, P3 del spec). | Constante hardcodeada: menos flexible; la env permite ajustar el periodo sin redeploy. |

## Contrato de interfaz

| Elemento                       | Interacción                                                                                                                                      |
| ------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| Comando `lists:purge-inactive` | Entrada: periodo desde configuración. Salida: reporte de conteo; exit 0 (también con 0 purgadas); abort con error si no hay periodo configurado. |

## Riesgos

| Riesgo                                                                  | Mitigación / por qué se acepta                                                                                                    |
| ----------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| `updated_at` no refleja modificaciones vía paths que no tocan el padre. | El plan usa el padre vía Eloquent con relación (items->save); mitigación: test que un update de ítem renueva la actividad (RF-2). |

## Estrategia de verificación

- Pest (`tests/Feature/PurgeInactiveListsCommandTest.php`): purga lista inactante; no toca activas; no purga lista modificada hace poco; reporta conteo; aborta sin periodo configurado.
- `php artisan test` + `vendor/bin/pint`.

## Cobertura de RF

| RF         | Parte del plan que lo cubre                                  |
| ---------- | ------------------------------------------------------------ |
| RF-1, RF-6 | Selección de listas inactivas por periodo (comando/modelo).  |
| RF-2       | Renovación vía `updated_at` (touchOwners al modificar ítem). |
| RF-3       | Borrado físico via `deleteWithItems()`.                      |
| RF-4       | Reporte de conteo (exit 0, también con 0).                   |
| RF-5       | Lectura del periodo desde configuración.                     |
| RF-6       | Abortar sin periodo configurado.                             |
