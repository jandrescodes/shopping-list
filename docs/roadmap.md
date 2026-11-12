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

- **Audit en el CI y monitor sobre `/up`** — job `audit` en `ci.yml`
  (`composer audit --locked` y `npm audit --omit=dev`; el audit completo de npm
  falla hoy por advisories en dependencias de desarrollo: `concurrently` y
  `follow-redirects`) y sección "Monitoreo externo" en `docs/deploy.md`. Sin
  spec ni release propio. Pendiente manual: dar de alta el monitor.

- **Compra semanal: reiniciar, duplicar y exportar como texto** — feature
  completa (T0-T18 + cierre), versión **1.3.0** (2026-10-23). Reiniciar compras
  con deshacer de 5 s, duplicar la lista como plantilla (ítems sin marcar,
  precios y moneda) y copiar la lista como texto plano. Validada RF por RF en
  `specs/003-compra-semanal/validation.md` (veredicto: spec cumplida; 212 Pest,
  58 Playwright y `pint --test` en verde, axe sin violaciones).
  → `specs/003-compra-semanal/`

- **Armar y planear la lista: reordenar, autocompletar y presupuesto** — feature
  completa (T0-T24 + cierre), versión **1.4.0**. Reordenar los pendientes con
  asa de arrastre o botones subir/bajar (orden compartido por todos los
  dispositivos y conservado al duplicar, reiniciar y deshacer), sugerencias de
  nombres desde los ítems de la propia lista y presupuesto opcional con aviso
  al superarlo. Validada RF por RF en
  `specs/004-armar-y-planear-la-lista/validation.md` (veredicto: spec
  cumplida; 267 Pest, 112 Playwright y `pint --test` en verde, axe sin
  violaciones). Verificada también con dos navegadores móviles emulados;
  falta la comprobación en dos celulares reales.
  → `specs/004-armar-y-planear-la-lista/`

- **Compartir con Web Share API y QR** — feature completa (T0-T5 + cierre),
  versión **1.5.0**. Añade el QR centrado del enlace absoluto, generado en el
  cliente sin peticiones, con botón mostrar/ocultar accesible, y conserva sin
  cambios el compartir por Web Share, portapapeles o URL en claro. Validada RF
  por RF en `specs/005-compartir-web-share-y-qr/validation.md` (267 Pest, 118
  Playwright, `pint --test` y axe en 375/1280 px en verde). Pendiente la
  comprobación manual en dos celulares reales.
  → `specs/005-compartir-web-share-y-qr/`

- **Limpieza de listas abandonadas** — feature completa (T0-T3 + cierre),
  versión **1.6.0** (2026-11-01). Añade el comando `lists:purge-inactive` que borra físicamente las listas sin uso ni
  modificación durante el periodo configurado (`SHOPPING_LIST_INACTIVE_MONTHS`),
  abortando si no está; lo ejecuta el cron de hPanel ya verificado (uid
  `fGLEKPsN2B`, `0 3 * * *`). Validada RF por RF en
  `specs/006-limpieza-de-listas-abandonadas/validation.md` (veredicto: spec
  cumplida; 274 Pest y `pint --test` en verde).
  → `specs/006-limpieza-de-listas-abandonadas/`

- **Mantenimiento técnico (sin spec, sin cambio de comportamiento)** — versión
  **1.6.1** (2026-11-12). El cálculo
  del orden de los ítems salió de `ShoppingList` a `App\Support\ItemOrdering`
  (puro, con tests unitarios sin BD); Larastan nivel 5 sin baseline en CI,
  Dependabot semanal (actions, composer, npm) y `Model::shouldBeStrict()` fuera
  de producción; `symfony/*` a 7.4.23 cierra los avisos de `composer audit`.
  Arreglado el intermitente de `list-throttle.spec.js` (espera la lista
  hidratada). 281 Pest, 118 Playwright, Larastan y `pint --test` en verde.

## Siguiente 🔜

_Nada en curso._ Pendiente manual: dar de alta el monitor externo sobre `/up`.

## Backlog / ideas 💡

Por orden de valor. Todas caben en el hosting compartido sin colas ni procesos
persistentes.

- **Modo oscuro** — mejora estética, baja prioridad.

Descartado a propósito (no recomendado para una lista familiar en hosting
compartido): categorías de ítems, historial de compras, notificaciones push y
migración a WebSockets/Reverb (chocan con la constitución y con el hosting).

> Cada feature nueva se crea como `specs/NNN-<slug>/` con `spec.md`, `plan.md` y
> `tasks.md` antes de tocar código, y no se cierra hasta que `/sdd:validate`
> deja su `validation.md`.
