# Roadmap

## Hecho ✅

- **Lista de compras familiar** (parcial) — T0-T14 completados:
  modelos, factories, ItemResource, escritura versionada, Form Requests y
  `ShoppingListController` (CRUD de listas) (57 Pest verdes).
  → `specs/001-lista-compras-familiar/tasks.md`

## Siguiente 🔜

- **Lista de compras familiar** (continuación) — T15-T33 pendientes:
  `ItemController` (ítems, sync, purgas), rutas + rate limiting, middleware,
  frontend/PWA.
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
