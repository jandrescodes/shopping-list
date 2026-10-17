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
  (`[1.2.1]`) y convenciones en `docs/ui-guidelines.md` ("Lecciones de UI"). 148 Pest +
  37 Playwright + axe sin violaciones a 375 y 1280px.

- **Endurecimiento tras auditoría de seguridad** — versión **1.2.2**
  (`[1.2.2]` en `CHANGELOG.md`). Auditoría de código parcial en dos
  pasadas sin hallazgos confirmados; se aplicó el hardening: `Referrer-Policy`,
  CORS sin comodín, cookie de sesión segura, workflows con permisos mínimos y
  actions por SHA, service worker con allowlist de caché y validación de
  `localStorage`. Sin spec propia (sin RF nuevos). 153 Pest + 37 Playwright.
  Pendiente a futuro: CSP (exige el build CSP de Alpine) y `trustProxies` si el
  hosting antepone un proxy.

- **Páginas de error, aviso de 429 y página offline** — versión **1.2.3**
  (`[1.2.3]` en `CHANGELOG.md`). Vistas 404, 500 y 503 en español,
  mensajes de 429 con `Retry-After` en `list.js` y `home.js`, y "Reintentar" en
  `offline.blade.php` (`CACHE_VERSION` `v3`). Sin spec propia (ajuste de
  interfaz, sin RF nuevos). Documentación derivada con `/sdd:docs`:
  `docs/schema.md` y `docs/app-flow.md`; constitución, specs y `AGENTS.md`
  alineados con las plantillas SDD. 158 Pest + 39 Playwright.

## Siguiente 🔜

- Sin feature activa. La próxima se crea como `specs/003-<slug>/`.

## Backlog / ideas 💡

Por orden de valor. Todas caben en el hosting compartido sin colas ni procesos
persistentes.

- **`composer audit` y `npm audit` en el CI** — un paso por job; habría
  detectado antes los advisories de `league/commonmark` y
  `symfony/http-foundation`. No necesita spec.
- **Monitor externo sobre `/up`** — aviso gratuito si Hostinger cae. Sin código.
- **Reordenar ítems** — columna `position` en `items` y arrastre con handle
  dedicado (no choca con el scroll en touch), con botones subir/bajar como
  alternativa accesible. Cubre el caso de agrupar por pasillo sin categorías.
- **Autocompletado de ítems frecuentes** — sugerencias desde el historial de
  la propia lista, sin catálogo global.
- **Presupuesto opcional por lista** — tope junto al total ya calculado
  (precio + moneda) con aviso al superarlo.
- **Compartir con Web Share API y QR** — `navigator.share` además de copiar el
  enlace; QR para pasar la lista a otro celular en casa.
- **Duplicar lista** — copia los ítems (sin marcar) a una lista nueva con otro
  `slug`, como plantilla de la compra semanal. Respeta el tope de
  `lists-create`.
- **Reiniciar compras** — desmarcar de una vez todos los ítems comprados para
  repetir la compra semanal sin borrar y recrear. Un `PATCH` y un botón en el
  menú de opciones.
- **Limpieza de listas abandonadas** — comando `lists:purge-inactive` (p. ej.
  12 meses sin cambios), ejecutado con el cron de hPanel. Verificar antes que
  el plan de hosting permite cron.
- **Modo oscuro** — mejora estética, baja prioridad.

Descartado a propósito (no recomendado para una lista familiar en hosting
compartido): categorías de ítems, historial de compras, notificaciones push y
migración a WebSockets/Reverb (chocan con la constitución y con el hosting).

> Cada feature nueva se crea como `specs/NNN-<slug>/` con `spec.md`, `plan.md` y
> `tasks.md` antes de tocar código, y no se cierra hasta que `/sdd:validate`
> deja su `validation.md`.
