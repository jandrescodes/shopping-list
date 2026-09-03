# Roadmap

## Hecho ✅

- **Lista de compras familiar** (parcial) — T0-T23 completados:
  modelos, factories, ItemResource, escritura versionada, Form Requests,
  `ShoppingListController` (CRUD de listas) e `ItemController` completo
  (`store` con tope de 200, `update` campo por campo, `destroy` soft delete,
  `purgePurchased`, `sync` con cursor/delta y sus casos límite), y
  `routes/api.php` con las 9 rutas del contrato sin auth y scoped bindings,
  rate limiting por IP (`lists-create` 10/h, `writes` 120/min, `sync` 60/min),
  middleware `NoIndex` (`X-Robots-Tag`) en `/l/{slug}` + `robots.txt`
  (100 Pest verdes).
  → `specs/001-lista-compras-familiar/tasks.md`

## Siguiente 🔜

- **Lista de compras familiar** (continuación) — T24-T33 pendientes:
  frontend (vistas Blade, Alpine) y PWA.
  → `specs/001-lista-compras-familiar/`

## Backlog / ideas 💡

- **Categorías de ítems** — agrupar por lácteos, limpieza, etc. Descartado para
  v1 por no ser un requisito claro.
- **Historial de compras pasadas** — ver qué se compró en semanas anteriores.
- **Notificaciones push** — avisar cuando alguien agrega un ítem, sin depender
  del polling.
- **Modo oscuro**.
- **Migración a WebSockets/Reverb** — si en el futuro se cambia de hosting
  compartido a un VPS.

> Cada feature nueva se crea como `specs/NNN-<slug>/` con `spec.md`, `plan.md` y
> `tasks.md` antes de tocar código, y no se cierra hasta que `/sdd:validate`
> deja su `validation.md`.
