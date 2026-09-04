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
- Lint/formato: `php artisan pint`
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
- Interfaz mobile-first. Un solo tema por ahora; modo oscuro es mejora futura.
- Package manager: `npm` (no pnpm). Dependencias de front con versión exacta
  pineada (`npm i --save-exact`); del front, a Hostinger sube `public/build/`
  (compilado en el runner), nunca `node_modules/`.

## Archivos / módulos clave

- `app/Models/ShoppingList.php`, `app/Models/Item.php`
- `app/Http/Controllers/Api/ShoppingListController.php`, `.../ItemController.php`
- `routes/api.php`
- `resources/js/app.js` (bootstrap + registro del service worker, global),
  `resources/js/home.js` (glue de la home, `@vite` solo ahí),
  `resources/js/list.js` (Alpine, versión pineada, global), `vite.config.js`
  (`input`)
- `public/manifest.json`, `public/sw.js`
- `.github/workflows/ci.yml` (tests + Pint en push/PR a `main`),
  `.github/workflows/deploy.yml` (deploy a Hostinger por `rsync` sobre SSH;
  release publicado o `workflow_dispatch`); `.env.production.example`
- `bootstrap/app.php`: render de excepciones. Todo `NotFoundHttpException` en
  `api/*` (o que espera JSON) responde `404 {"message":"Not Found"}` uniforme —
  lista borrada, slug inexistente o ruta desconocida son indistinguibles (RF-4).
  También registra `App\Http\Middleware\Hsts` de forma global (`Strict-
  Transport-Security`, todas las respuestas), aparte del alias `noindex` que
  solo aplica a `/l/{slug}`.

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
- `docs/` y `specs/` se versionan pero **no se despliegan**: quedan fuera del
  `rsync` de despliegue y del docroot.
- **Despliegue**: `.github/workflows/deploy.yml` (release publicado o
  `workflow_dispatch`). Construye `vendor/` y `public/build/` en el runner y los
  sube por `rsync` sobre SSH; el servidor **nunca** hace `git pull` ni corre
  `composer`. Pasos y post-deploy en `docs/deploy.md`. Al tocar cualquier
  workflow, verifica de paso que sus `uses:` no quedaron atrás y bumpéalos en el
  mismo commit.
- No subas `.env`. No asumas colas ni schedulers sin verificar el plan de hosting.
- No añadas WebSockets/Reverb ni autenticación sin cambiar antes la constitución.
- **Vistas Blade sin `<script>`/`<style>` inline** (salvo `offline.blade.php`,
  ver su propio comentario: fallback cacheado por el SW que debe renderizar
  sin build ni red). JS de página va a su propio módulo en `resources/js/` y
  se registra como entrada en `vite.config.js`; si es solo para una vista, se
  incluye con `@vite([...])` dentro de `@section('scripts')` en esa vista, y
  `layout.blade.php` lo imprime con `@yield('scripts')` al final de `<body>`.
  **Gotcha de orden**: `list.js` es la entrada global que llama
  `Alpine.start()`; si se añade una entrada de página (como `home.js`) que
  registra sus propios `Alpine.data()` en `alpine:init`, esa entrada se
  imprime *después* de `list.js` en el HTML (va en `@yield('scripts')`, al
  final de `<body>`), así que si `Alpine.start()` corriera de forma síncrona
  al final de `list.js` dispararía `alpine:init` antes de que el módulo de
  la página hubiera corrido, dejando sus componentes sin definir
  (`ReferenceError` en consola). Por eso `Alpine.start()` va envuelto en un
  listener de `DOMContentLoaded` (dispara después de que todos los `<script
  type="module">` — deferred por naturaleza — ya corrieron y registraron sus
  componentes).

## Al terminar cualquier tarea

- Ejecuta `php artisan test` y confirma en la respuesta que pasa.
- Si tocaste la capa de cliente (`resources/js/`), ejecuta también
  `npx playwright test` y confírmalo.
- Ejecuta `php artisan pint` sobre los archivos tocados.

## Planificación de features (Spec-Driven Development)

Este proyecto se lleva con SDD: primero la spec, luego el plan, luego las
tareas, y solo entonces el código. **La spec es el contrato: si algo no está en
ella, no se implementa.**

### Artefactos

```
docs/constitution.md          ← principios del proyecto (nivel proyecto)
docs/roadmap.md               ← hecho / en curso / backlog (nivel proyecto)
specs/NNN-<slug>/
├── spec.md                   ← QUÉ y POR QUÉ (RF en EARS) + § Clarificaciones
├── plan.md                   ← CÓMO (módulos, datos, decisiones, riesgos)
├── tasks.md                  ← tareas <30 min + bloque de cierre
└── validation.md             ← recorrido RF por RF + veredicto (fase 7)
```

Una feature no está cerrada hasta que existen sus cuatro archivos.

### Fases

| #   | Fase           | Command              |
| --- | -------------- | -------------------- |
| 1   | Constitución   | `/sdd:constitution`  |
| 2   | Especificación | `/sdd:spec`          |
| 3   | Clarificación  | `/sdd:clarify`       |
| 4   | Planificación  | `/sdd:plan`          |
| 5   | Tareas         | `/sdd:tasks`         |
| 6   | Implementación | `/sdd:implement <n>` |
| 7   | Validación     | `/sdd:validate`      |
| 8   | Cambio         | `/sdd:change <req>`  |

### Reglas

- **Numeración `specs/NNN`**: contador propio de `specs/`, tres dígitos,
  monótono, sin reutilizar números borrados. Empieza en `001`; la próxima
  feature es `002`. No es el ordinal del roadmap ni un RF histórico.
- **Verificación como puerta**: ninguna tarea se marca `[x]` sin
  `php artisan test` en verde y, si tocó `resources/js/`, también
  `npx playwright test`.
- El bloque `## Cierre de la feature` de `tasks.md` va fuera de la numeración
  `Tn` y no se implementa con `/sdd:implement`.
- No modificar `specs/` fuera de su fase salvo petición explícita.
