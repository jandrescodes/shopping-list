# Tasks — Spec 006

Tareas de <30 min, ordenadas por dependencia. Cada una: sus RF, un checkbox y
una línea "Done when:" verificable. Derivadas de `plan.md`; los RF salen de
`spec.md`.

Puerta de verificación por tarea (`AGENTS.md`): `php artisan test` y
`vendor/bin/pint` sobre lo tocado.

Reglas que atraviesan todo el bloque:

- **No se toca el contrato de la API ni el sync** (constitución 5, fuera de
  alcance): ninguna tarea añade rutas ni cambia controladores/views.
- **No se introduce scheduler en la app** (constitución 5, P7): el comando se
  expone para el cron ya creado (uid `fGLEKPsN2B`).

## Comando: `lists:purge-inactive`

- [x] T0. Baseline: ejecutar `php artisan test` y `vendor/bin/pint --test`
      sin cambios. Done when: pasan y los conteos quedan registrados.
      Evidencia (2026-11-01): `pint --test` → passed; `php artisan test` →
      267 passed (1437 assertions), sin cambios en el repo (solo docs
      `specs/006/`).

- [x] T1. Comando `lists:purge-inactive`: leer periodo desde configuración y
      abortar con error si no está. (RF-5, RF-6)
      Done when: Pest cubre el aborto sin periodo configurado.
      Evidencia (2026-11-01): `tests/Feature/PurgeInactiveListsCommandTest.php`
      → 2 passed (aborto sin periodo + lectura desde configuración);
      suite completa 269 passed (1440 assertions) y `pint --test` passed.

- [x] T2. Comando `lists:purge-inactive`: seleccionar listas inactivas
      (`updated_at` < periodo), borrar físico vía `deleteWithItems()` y reportar
      conteo. (RF-1, RF-3, RF-4)
      Done when: Pest cubre purga de inactiva, no toca activas y reporta conteo.
      Evidencia (2026-11-01): `scopeInactiveSince` en `ShoppingList` + purge en
      `PurgeInactiveLists`; tests del comando → 5 passed (purga con ítems
      activos y tombstones, no toca activas, reporta conteo); suite completa
      272 passed (1448 assertions) y `pint --test` passed.

## Tests del comando

- [x] T3. `tests/Feature/PurgeInactiveListsCommandTest.php`: purga lista
      inactante; no toca listas activas; no purga lista modificada hace poco;
      reporta conteo; aborta sin periodo. (RF-1..RF-6)
      Done when: `php artisan test` en verde.
      Evidencia (2026-11-01): archivo completo con 7 tests (aborto, config,
      purga con ítems, activas intactas, conteo, renovación vía PATCH real
      de ítem — RF-2 — y caso 0); suite completa 274 passed (1453 assertions)
      y `pint --test` passed. Mecanismo RF-2 verificado en el vendor:
      `ListVersion::write` → `bumpVersion` → `Eloquent\Builder::increment`
      añade `updated_at` (`addUpdatedAtColumn`).

## Cierre de la feature

- [x] Suite completa: `php artisan test` y `vendor/bin/pint` pasan sin cambios.
      Evidencia (2026-11-01): 274 passed (1453 assertions) + `pint --test`
      passed.
- [x] Validación SDD: ejecutar `/sdd:validate` para generar
      `validation.md`, recorrer RF-1 a RF-6 y emitir el veredicto.
      Evidencia: `specs/006-limpieza-de-listas-abandonadas/validation.md` —
      veredicto spec cumplida; `docs/roadmap.md` movido a Hecho ✅.

## Cobertura RF → tarea

| RF   | Tareas |
| ---- | ------ |
| RF-1 | T2     |
| RF-2 | T2, T3 |
| RF-3 | T2     |
| RF-4 | T2     |
| RF-5 | T1     |
| RF-6 | T1     |
