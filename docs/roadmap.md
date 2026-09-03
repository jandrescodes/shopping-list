# Roadmap

## Hecho ✅

- **Lista de compras familiar** (parcial) — T0-T31 + T35-T36 completados:
  modelos, factories, ItemResource, escritura versionada, Form Requests,
  `ShoppingListController` (CRUD de listas) e `ItemController` completo
  (`store` con tope de 200, `update` campo por campo, `destroy` soft delete,
  `purgePurchased`, `sync` con cursor/delta y sus casos límite), y
  `routes/api.php` con las 9 rutas del contrato sin auth y scoped bindings,
  rate limiting por IP (`lists-create` 10/h, `writes` 120/min, `sync` 60/min),
  middleware `NoIndex` (`X-Robots-Tag`) en `/l/{slug}` + `robots.txt`;
  vistas Blade (T24-T27): `layout` PWA (manifest, registro de SW),
  `home` (form de creación + "mis listas" desde `localStorage`),
  `list` (cabecera editable, alta, orden RF-18, "limpiar comprados") y
  `offline` estática; `list.js` núcleo Alpine (T28): carga vía `show`, render
  reactivo con `x-text`, alta/edición/marcado/borrado sin UI optimista,
  `PATCH` de solo los campos que cambian; `list.js` memoria local (T29):
  directorio "mis listas" en `localStorage` (guardar al abrir, refrescar
  nombre al renombrar, podar con 404, tope de 20) y nombre de "quién agrega"
  recordado y propuesto editable; polling (T30): consulta a `sync` cada 3–4 s
  con el último cursor, fusión por `id` + poda de `deleted_ids` + reorden RF-18,
  pausa con `visibilitychange` y consulta inmediata al volver al foco, aviso
  "esta lista ya no existe" si `sync` responde 404; desconexión (T31): banner
  "sin conexión" y última lectura visible, escrituras fallan con aviso sin
  encolar, `online` dispara resync inmediato; cambio fase 8 — navegación al home
  (T35: `<nav>` "Mis listas" en el layout, RF-33) y compartir enlace (T36:
  `navigator.share` → portapapeles → URL en claro, RF-34)
  (111 Pest + 22 Playwright verdes).
  → `specs/001-lista-compras-familiar/tasks.md`

## Siguiente 🔜

- **Lista de compras familiar** (continuación) — T32-T34 pendientes: PWA
  (`manifest.json`, iconos, `sw.js`) y comando de purga de lápidas.
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
