# AGENTS.md — Lista de compras familiar

## Proyecto

PWA de listas de compras compartidas en familia. API REST en Laravel 12 + MySQL;
frontend Blade + Alpine.js instalable como PWA (manifest + service worker).
Sincronización entre dispositivos por polling HTTP (sin WebSockets), para poder
correr en el hosting compartido Premium de Hostinger. Sin cuentas de usuario: el
`slug` no adivinable de cada lista es la única llave de acceso.

## Comandos

- Ejecutar: `php artisan serve`
- Tests API/persistencia: `php artisan test` (Pest 3.8). Corren contra MySQL,
  no SQLite: copiar `.env.testing.example` a `.env.testing` y crear la BD
  `shopping_list_testing` una vez. Se usa MySQL para ejercitar los row locks
  de `App\Support\ListVersion` y los tipos/largos de columna reales.
- Tests de navegador (capa de cliente): `npx playwright test`. `playwright.config.js`
  levanta su propio server (`npm run build` + `migrate:fresh` + `serve` con
  `APP_ENV=testing`, puerto 8199). Requisito: `.env.testing` debe fijar
  `SESSION_DRIVER=cookie` — el saneamiento de T0 borró la tabla `sessions`, así
  que con el driver `database` por defecto el server HTTP responde 500 y el
  `webServer` de Playwright hace timeout (Pest no lo detecta: no toca sesión).
  El `webServer` arranca con `E2E_RELAXED_LIMITS=1`: el `php artisan serve` es
  un proceso largo cuyo estado de rate limiting se acumula durante toda la
  corrida, así que ese flag sube los topes de `bootstrap/app.php`. Pest no lo
  fija y ejercita los límites reales (`RateLimitingTest`).
  Locators Playwright: no selecciones por rol ARIA ancho (`p[role="status"]`,
  `[role="alert"]`) — la vista de lista tiene varios avisos que comparten rol
  (error, offline, "enlace copiado"). Scopea por la clase de fondo del aviso
  concreto (`.bg-amber-50`, `.bg-green-50`, …) o `hasText`.
  Primera vez: `npx playwright install chromium`.
- Lint/formato: `vendor/bin/pint` (`php artisan pint` no existe: Pint no
  registra comando de Artisan en este repo)
- Assets: `npm run build`

> PHP 8.2 (XAMPP) fija el techo: Pest 4 y `pest-plugin-browser` exigen 8.3+, así
> que los tests de navegador van con Playwright directo, no dentro de Pest.

## Estilo y convenciones

- PHP 8.2, Laravel 12. `snake_case` en BD, `camelCase` en Eloquent/PHP.
- Todo el código en inglés: variables, métodos, funciones, clases, campos de
  BD, rutas y nombres de test. El español se limita a los textos visibles de
  las vistas Blade y a `lang/es/`. La documentación (`docs/`, `specs/`) también
  en español.
- Validación de entradas con Form Requests.
- URLs públicas de una lista por `slug`, nunca por `id`.
- Interfaz mobile-first que también funciona en desktop: en `md+` el contenido
  va en una card centrada con barra de marca encima (`layout.blade.php`). Un
  solo tema por ahora; modo oscuro es mejora futura.
- Package manager: `npm` (no pnpm). Dependencias de front con versión exacta
  pineada (`npm i --save-exact`); del front, a Hostinger sube `public/build/`
  (compilado en el runner), nunca `node_modules/`.

## Archivos / módulos clave

- `app/Models/ShoppingList.php`, `app/Models/Item.php`
- `app/Http/Controllers/Api/ShoppingListController.php`, `.../ItemController.php`
- `routes/api.php`: además del CRUD, `POST /lists/{list}/duplicate`
  (`throttle:lists-create`, comparte cupo con crear lista) y, bajo
  `throttle:writes`, `items/reset-purchased` y `items/restore-purchased`
  (reiniciar compras y su deshacer: una petición cada uno, el servidor filtra
  por el estado de la BD).
- `resources/js/app.js` (bootstrap + registro del service worker, global),
  `resources/js/home.js` (glue de la home, `@vite` solo ahí),
  `resources/js/list.js` (Alpine, versión pineada, global),
  `resources/js/throttle.js` (texto del 429 de listas en minutos, lo importan
  `home.js` y `list.js`; no es entrada de Vite), `vite.config.js` (`input`)
- `public/manifest.json`, `public/sw.js`: la rama cache-first solo guarda el shell
  y `/build/*` (`isCacheable`); subir `CACHE_VERSION` al cambiar el shell, porque
  no revalida. `myShoppingLists` (localStorage) se valida con `isValidEntry` en
  `home.js` y `list.js`: array de `{slug, name}` con `slug` `[A-Za-z0-9_-]+`.
- `resources/views/errors/` (`404` y `503` sobre `errors/layout`; `500`
  standalone): páginas de error web en español; el 404 es idéntico exista o no
  la lista. El `500` no usa el layout ni `@vite` para renderizar aunque el
  build falle. `offline.blade.php` queda aparte (standalone, ver Reglas) y su
  cambio exige subir `CACHE_VERSION` de `sw.js`. Un 429 de escritura lo
  traduce `writeError` en `list.js` (usa `Retry-After`).
- `.github/workflows/ci.yml` (tests, Pint y audit de dependencias en push/PR a `main`),
  `.github/workflows/deploy.yml` (deploy a Hostinger por `rsync` sobre SSH;
  release publicado o `workflow_dispatch`); `.env.production.example`
- `bootstrap/app.php`: render de excepciones. Todo `NotFoundHttpException` en
  `api/*` (o que espera JSON) responde `404 {"message":"Not Found"}` uniforme —
  lista borrada, slug inexistente o ruta desconocida son indistinguibles (RF-4).
  También registra `App\Http\Middleware\Hsts` de forma global (`Strict-
Transport-Security`, todas las respuestas) y `ReferrerPolicy` (`no-referrer`:
  el slug viaja en la URL), aparte del alias `noindex` que solo aplica a
  `/l/{slug}`. `config/cors.php` deja `allowed_origins` vacío a propósito: el
  frontend es same-origin y no debe haber CORS comodín en `api/*`. Todo
  `api/*` responde JSON aunque falte `Accept` (`shouldRenderJsonWhen`): sin
  eso, un 422 sería un `302` a HTML.

## Reglas

- Lee `docs/constitution.md` y la spec activa en `specs/` antes de tocar código.
- La constitución manda: si una feature choca con ella, se replantea la feature.
- Nuevas convenciones/arquitectura se documentan aquí (`AGENTS.md`), no en
  `CLAUDE.md`.
- No modifiques archivos dentro de `specs/` salvo petición explícita.
- Al commitear un bloque de tareas, actualiza en `docs/roadmap.md` los rangos
  `Tn` de "Hecho parcial" y "Siguiente 🔜". El paso a "Hecho ✅" del cierre de
  la feature lo hace `/sdd:validate`.
- **Precedencia**: `docs/constitution.md` manda sobre specs y planes. Ante
  conflicto entre `docs/`/`specs/` y este archivo, este archivo es la fuente
  operativa del **código** y `docs/`/`specs/` la capa de **planificación**.
- `docs/`, `specs/` y `deliverables/` se versionan (o se ignoran) pero **no se
  despliegan**: quedan fuera del `rsync` de despliegue y del docroot.
- **Despliegue**: `.github/workflows/deploy.yml` (release publicado o
  `workflow_dispatch`). Construye `vendor/` y `public/build/` en el runner y los
  sube por `rsync` sobre SSH; el servidor **nunca** hace `git pull` ni corre
  `composer`. Pasos y post-deploy en `docs/deploy.md`. Al tocar cualquier
  workflow, verifica de paso que sus `uses:` no quedaron atrás y bumpéalos en el
  mismo commit. Las actions van fijadas por SHA de commit con el tag en un
  comentario (`@<sha> # v7.0.1`; `setup-php` apunta a un tag anotado: usa el
  commit al que resuelve). Ambos workflows declaran `permissions: contents: read`.
  `bootstrap/cache/` se sincroniza por rsync a propósito: el servidor no corre `composer`, así que no puede
  regenerar `packages.php`/`services.php`.
- No subas `.env`. No asumas colas ni schedulers sin verificar el plan de hosting.
- No añadas WebSockets/Reverb ni autenticación sin cambiar antes la constitución.
- **Vistas Blade sin `<script>`/`<style>` inline** (salvo `offline.blade.php`,
  fallback cacheado por el SW que debe renderizar sin build ni red, y
  `errors/500.blade.php`, que no puede depender del build; ver su comentario). JS de página va a su propio módulo en `resources/js/` y
  se registra como entrada en `vite.config.js`; si es solo para una vista, se
  incluye con `@vite([...])` dentro de `@section('scripts')` en esa vista, y
  `layout.blade.php` lo imprime con `@yield('scripts')` al final de `<body>`.
  `Alpine.start()` va envuelto en `DOMContentLoaded` para que las entradas de
  página (`home.js`) registren sus `Alpine.data()` antes del arranque; ver
  `docs/ui-guidelines.md`.
- **Las clases de fondo de los avisos son reservadas para los tests e2e**:
  `list-*.spec.js` localizan cada aviso con `#list-app .bg-<color>`
  (`.bg-amber-50` offline, `.bg-green-50` copiado, `.bg-gray-100` URL en
  claro), así que **un elemento nuevo no debe reutilizar esas clases**. Cada
  componente nuevo lleva su propio `id` (p. ej. `#currency-chip`) o una
  clase semántica, y se localiza con eso. Fondos ya tomados por avisos
  propios: `bg-sky-50` (`#reset-undo`, reinicio de compras) y `bg-teal-50`
  (`#export-notice`, lista copiada como texto).
- **Una sola ranura de deshacer** en `list.js`: el borrado de ítem
  (`pendingUndo`) y el reinicio de compras (`resetUndo`) comparten
  `undoTimer` y se limpian mutuamente; el último aviso reemplaza al anterior,
  para que "Deshacer" siempre signifique lo último que se hizo. Rompido en real: el chip de moneda
  se estilizó con `bg-gray-100` y `list-share.spec.js:70` falló con
  "strict mode violation: resolved to 2 elements".

## Al terminar cualquier tarea

- Ejecuta `php artisan test` y confirma en la respuesta que pasa.
- Si tocaste la capa de cliente (`resources/js/`), ejecuta también
  `npx playwright test` y confírmalo.
- Ejecuta `vendor/bin/pint` sobre los archivos tocados.

## Planificación de features (Spec-Driven Development)

Este proyecto se lleva con SDD: primero la spec, luego el plan, luego las
tareas, y solo entonces el código. **La spec es el contrato: si algo no está en
ella, no se implementa.**

### Artefactos

```
docs/constitution.md          ← principios del proyecto (nivel proyecto)
docs/roadmap.md               ← hecho / en curso / backlog (nivel proyecto)
docs/schema.md, app-flow.md   ← vistas derivadas de `/sdd:docs` (no editar a mano
                                salvo `Notes (hand-written)` y los `TODO: provide`)
deliverables/trd.md           ← derivado, fuera de Git (`.gitignore`)
specs/NNN-<slug>/
├── spec.md                   ← QUÉ y POR QUÉ (RF en EARS) + § Clarificaciones
├── plan.md                   ← CÓMO (módulos, datos, decisiones, riesgos)
├── tasks.md                  ← tareas <30 min + bloque de cierre
└── validation.md             ← recorrido RF por RF + veredicto (fase 7)
```

Una feature no está cerrada hasta que existen sus cuatro archivos.

### Fases

| #   | Fase           | Comando (plugin · script)                   |
| --- | -------------- | ------------------------------------------- |
| 1   | Constitución   | `/sdd:constitution` · `/sdd-constitution`   |
| 2   | Especificación | `/sdd:spec` · `/sdd-spec`                   |
| 3   | Clarificación  | `/sdd:clarify` · `/sdd-clarify`             |
| 4   | Planificación  | `/sdd:plan` · `/sdd-plan`                   |
| 5   | Tareas         | `/sdd:tasks` · `/sdd-tasks`                 |
| 6   | Implementación | `/sdd:implement <n>` · `/sdd-implement <n>` |
| 7   | Validación     | `/sdd:validate` · `/sdd-validate`           |
| 8   | Cambio         | `/sdd:change <req>` · `/sdd-change <req>`   |

Transversal, no es una novena fase: `/sdd:docs
<prd|trd|acceptance|changes|delivery|schema|flow|ux> [feature]` genera un
documento derivado de los artefactos, tras aprobar una propuesta.

### Reglas

- **Numeración `specs/NNN`**: contador propio de `specs/`, tres dígitos,
  monótono, sin reutilizar números borrados. Empieza en `001`; la próxima
  feature es `002`. No es el ordinal del roadmap ni un RF histórico.
- Docs profile: lite
- **Verificación como puerta**: ninguna tarea se marca `[x]` sin
  `php artisan test` en verde y, si tocó `resources/js/`, también
  `npx playwright test`.
- El bloque `## Cierre de la feature` de `tasks.md` va fuera de la numeración
  `Tn` y no se implementa con `/sdd:implement`.
- No modificar `specs/` fuera de su fase salvo petición explícita.

### Lecciones de tests

- Un fixture/trait que haga `DROP`/`CREATE` sobre un recurso de nombre fijo (BD, archivo) debe sufijarlo con un identificador por proceso (`getmypid()`) si los tests corren en paralelo (paratest).
- Un test que resetea un singleton/global compartido (conexión de BD, config) debe restaurar el doble de test en `tearDown`, no dejarlo en `null`: con orden aleatorio, los tests siguientes intentarían conectar al recurso real. "Pasa en local" no prueba nada si local tiene el recurso real; para reproducir CI corre sin `.env`/`.env.testing` y con `--order-by=default`.
- Nunca calcules la fecha límite de un test con `date()`/`strtotime()` para compararla contra `NOW()`/`CURDATE()` de la BD: el reloj/timezone del proceso y el del servidor de BD pueden diferir. Usa una expresión SQL (`NOW() - INTERVAL 1 HOUR`).

### Lecciones de Laravel

- `Schema::getColumns()` devuelve `default` como el string `'NULL'` (no `null`) en MariaDB para una columna sin default; un `->toBeNull()` en un test de schema falla aunque el esquema esté bien. Usa `->toBeIn([null, 'NULL'])` o, mejor, un assert de comportamiento (insertar sin la columna y leer). Vigila que `ADD col ... DEFAULT 0` rellena las filas existentes con `0`, corrupción silenciosa donde `NULL` significa "sin valor".

### Lecciones de UI

Reglas: targets táctiles de 44px reales (`min-h-11`), nunca `text-gray-400`
sobre blanco, y verificar con axe-core a 375 y 1280px más una pasada de
teclado. Detalle y ejemplos en `docs/ui-guidelines.md`.
