# Changelog

Todos los cambios notables de este proyecto se documentan aquí.

El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y el proyecto se adhiere a [Versionado Semántico](https://semver.org/lang/es/).

## [Sin publicar]

_Nada por ahora._

## [1.1.0] - 2026-09-04

### Añadido

- **HSTS.** Middleware `App\Http\Middleware\Hsts` (`Strict-Transport-Security`)
  registrado de forma global en `bootstrap/app.php`, aparte del alias `noindex`.
- Cache de `vendor/` (Composer) en el job `deploy` de
  `.github/workflows/deploy.yml`, con key propia separada del job `test`
  (`--no-dev` da un árbol de dependencias distinto).
- **Deshacer borrado de ítem.** Ventana de gracia de 5 s tras eliminar un
  ítem: "deshacer" lo recrea con el mismo nombre/cantidad/"quién agrega"/
  estado de comprado (nuevo `id`, no revierte el `DELETE`).

### Cambiado

- **Vistas Blade sin `<script>`/`<style>` inline** (salvo `offline.blade.php`).
  El JS de `home.blade.php` y el registro del service worker de
  `layout.blade.php` se movieron a `resources/js/home.js` y
  `resources/js/app.js` respectivamente, cada uno como entrada de Vite.
  `layout.blade.php` ahora imprime `@yield('scripts')` al final de `<body>`.
- **Fat model / thin controller.** La lógica de ordenamiento, sincronización
  por cursor, límite de 200 ítems, purga de comprados y borrado en cascada se
  movió de `ItemController`/`ShoppingListController` a métodos en
  `App\Models\ShoppingList` y un scope en `App\Models\Item`.
- **Ajustes visuales y de accesibilidad** (hallazgos de `/impeccable`): tarjeta
  propia para `<main>` en pantallas md+, contraste y anillo de foco en
  inputs/botones, áreas táctiles ampliadas vía pseudo-elemento `::before`,
  aviso de "enlace copiado" con auto-dismiss a los 5 s.
- Comentarios de código: se quitaron las referencias a `RF-##`/`RNF`/`T##`
  (rotan con la spec; el contrato vive en `specs/`, no en comentarios).

### Eliminado

- Referencias a requisitos (`RF-4`, `RF-18`, etc.) en comentarios de
  `bootstrap/app.php` y `routes/web.php`.

## [1.0.0] - 2026-09-03

Primera versión funcional. Implementa completa la feature 001 "Lista de compras
compartida" (spec, plan, tareas y validación en `specs/001-lista-compras-familiar/`).

### Añadido

- **Listas sin cuentas.** Crear lista con nombre (1–60 caracteres); cada lista
  recibe un `slug` opaco de 128 bits (base64url, 22 caracteres) que es la única
  llave de acceso. La respuesta de creación devuelve el enlace público absoluto
  derivado de `APP_URL`.
- **API REST** (`routes/api.php`, 9 endpoints sin autenticación): crear, ver,
  renombrar y eliminar listas; agregar, editar, marcar/desmarcar, eliminar y
  purgar ítems; sincronización por cursor.
- **Ítems.** Nombre (1–100), cantidad y "quién agrega" opcionales (texto libre
  ≤50, recortados); varios ítems con el mismo nombre coexisten; tope de 200
  ítems activos por lista. Orden fijado por el servidor: no comprados primero,
  luego comprados, cada grupo por fecha de creación ascendente.
- **Eliminar ítem** = borrado lógico con lápida (tombstone) para la
  sincronización. **Eliminar lista** = borrado físico de la lista y todos sus
  ítems.
- **"Limpiar comprados"** en una sola pasada, evaluando el estado en la base de
  datos, previa confirmación.
- **Contador de versión por lista** (`App\Support\ListVersion`): incremento
  atómico con row lock en cada escritura; sella las filas afectadas. Resolución
  de conflictos campo por campo, última escritura gana, sin aviso.
- **Sincronización entre dispositivos por polling HTTP** (sin WebSockets):
  `GET /api/lists/{slug}/items?cursor=` devuelve el delta (`version > cursor`) o
  la carga completa si el cursor falta o es inválido. El cliente consulta cada
  3–4 s, fusiona por `id`, quita `deleted_ids` y recoloca por la regla de orden;
  pausa con la pestaña oculta y reanuda con consulta inmediata.
- **PWA instalable.** `public/manifest.json` con iconos 192×192 y 512×512,
  `theme_color` y `display: standalone`; `public/sw.js` con precache del app
  shell (cache-first para estáticos, red primero para navegación con fallback a
  `/offline`, solo-red para `/api/*`).
- **Modo sin conexión.** La última lectura conocida permanece visible; las
  escrituras fallan con aviso y no se encolan; el polling recupera el estado al
  volver la red. Página `/offline` mínima para el primer arranque sin caché.
- **Frontend** Blade + Alpine.js (`resources/js/list.js`): render reactivo con
  `x-text` (nunca `x-html`), sin UI optimista, `PATCH` de solo los campos que
  cambian.
- **Memoria local del navegador.** Directorio "mis listas" en `localStorage`
  (guardar al abrir, refrescar el nombre al renombrar, podar al recibir 404,
  tope de 20); nombre de "quién agrega" recordado y propuesto editable.
- **Navegación y compartir.** Enlace "Mis listas" de vuelta al home en la vista
  de lista; acción "Compartir" con `navigator.share`, con degradación a
  `navigator.clipboard` y a mostrar la URL en claro.
- **Seguridad del contenido.** `name`, `quantity` y `added_by` se tratan como
  texto plano en servidor y cliente.
- **Límite de peticiones por IP:** crear lista 10/hora, resto de escrituras
  120/min, sincronización 60/min (429 al exceder).
- **No indexable.** `X-Robots-Tag: noindex, nofollow` en `/l/{slug}` y
  `robots.txt` con `Disallow: /l/`.
- **Internacionalización.** Interfaz en español; `APP_LOCALE=es`,
  `lang/es/validation.php` publicado.
- **Comando de mantenimiento** `php artisan items:purge-tombstones --before=<fecha>`:
  borra físicamente las lápidas anteriores a la fecha; aborta sin `--before`.
- **Documentación.** `README.md`, `CHANGELOG.md`, `AGENTS.md` (fuente única de
  convenciones), `docs/constitution.md`, `docs/roadmap.md`, `docs/deploy.md`.
- **Tests.** 119 Pest 3.8 (API y persistencia, contra MySQL) + 22 Playwright
  (capa de cliente).
- **CI/CD.** `.github/workflows/ci.yml` (Pest + Pint en push/PR a `main`) y
  `.github/workflows/deploy.yml` (deploy a Hostinger: tests → build en el runner
  → `rsync` sobre SSH → `artisan migrate`/caches → health check; se dispara al
  publicar un release o con `workflow_dispatch`). Plantilla
  `.env.production.example` para el `.env` del servidor.

### Eliminado

- Andamiaje de autenticación de Laravel: modelo `User`, migraciones
  `users`/`sessions`/`password_reset_tokens`/`personal_access_tokens`/`jobs`,
  dependencia `laravel/sanctum` y ruta `GET /user` (RF-30).

[Sin publicar]: https://github.com/jandrescodes/shopping-list/compare/1.1.0...HEAD
[1.1.0]: https://github.com/jandrescodes/shopping-list/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/jandrescodes/shopping-list/releases/tag/1.0.0
