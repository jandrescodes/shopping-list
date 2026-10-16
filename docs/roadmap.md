# Roadmap

## Hecho ✅

- **Lista de compras familiar** — feature completa (T0-T36 + cierre), versión
  **1.0.0** (2026-09-03). Validada RF por RF en
  `specs/001-lista-compras-familiar/validation.md` (veredicto: spec cumplida;
  119 Pest + 22 Playwright + `pint --test` en verde). Procedimiento de
  despliegue en `docs/deploy.md`. Historial en `CHANGELOG.md`.

    Cubre: modelos + factories + `ItemResource`, escritura versionada
    (`App\Support\ListVersion`), Form Requests, `ShoppingListController` (CRUD de
    listas) e `ItemController` completo (`store` con tope de 200, `update` campo
    por campo, `destroy` soft delete, `purgePurchased`, `sync` con cursor/delta),
    `routes/api.php` con las 9 rutas del contrato sin auth y scoped bindings,
    rate limiting por IP (`lists-create` 10/h, `writes` 120/min, `sync` 60/min),
    middleware `NoIndex` + `robots.txt`; vistas Blade (`layout` PWA, `home`,
    `list`, `offline`); `list.js` (Alpine): carga sin UI optimista, `PATCH`
    parcial, memoria local "mis listas" + "quién agrega", polling cada 3–4 s con
    pausa por `visibilitychange`, manejo de desconexión, navegación al home y
    compartir enlace; PWA (`manifest.json` + iconos 192/512, `sw.js` con precache
    del shell); comando `items:purge-tombstones --before=<fecha>`.

    Verificación manual pendiente en el despliegue: Lighthouse "installable",
    shell offline en primer arranque y demo de sync en dos celulares.
    → `specs/001-lista-compras-familiar/`

- **Precios de ítems, moneda de la lista y cantidad editable** — implementación
  completa (T0-T25), versión **1.2.0**. Validada RF por RF en
  `specs/002-precios-y-moneda/validation.md` (automatización en verde;
  verificación manual en dos celulares pendiente).
  → `specs/002-precios-y-moneda/`

- **Pulido de UI/UX responsive (desktop + mobile)** — versión **1.2.1**. Filas
  de ítem rediseñadas, menú de opciones, formulario simplificado, un solo aviso
  de conexión, contraste y targets de 44px, foco de teclado y card en desktop.
  Sin spec propia (ajuste de interfaz, sin RF nuevos); detalle en `CHANGELOG.md`
  (`[1.2.1]`) y convenciones en `AGENTS.md` ("Lecciones de UI"). 148 Pest +
  37 Playwright + axe sin violaciones a 375 y 1280px.

- **Endurecimiento tras auditoría de seguridad** — versión **1.2.2**
  (`[1.2.2]` en `CHANGELOG.md`). Auditoría de código parcial en dos
  pasadas sin hallazgos confirmados; se aplicó el hardening: `Referrer-Policy`,
  CORS sin comodín, cookie de sesión segura, workflows con permisos mínimos y
  actions por SHA, service worker con allowlist de caché y validación de
  `localStorage`. Sin spec propia (sin RF nuevos). 153 Pest + 37 Playwright.
  Pendiente a futuro: CSP (exige el build CSP de Alpine) y `trustProxies` si el
  hosting antepone un proxy.

## Siguiente 🔜

- Sin feature activa. La próxima se crea como `specs/003-<slug>/`.

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
