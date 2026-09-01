# AGENTS.md — Lista de compras familiar

## Proyecto

PWA de listas de compras compartidas en familia. API REST en Laravel 12 + MySQL;
frontend Blade + Alpine.js instalable como PWA (manifest + service worker).
Sincronización entre dispositivos por polling HTTP (sin WebSockets), para poder
correr en el hosting compartido Premium de Hostinger. Sin cuentas de usuario: el
`slug` no adivinable de cada lista es la única llave de acceso.

## Comandos

- Ejecutar: `php artisan serve`
- Tests: `php artisan test`
- Lint/formato: `php artisan pint`
- Assets: `npm run build`

## Estilo y convenciones

- PHP 8.2, Laravel 12. `snake_case` en BD, `camelCase` en Eloquent/PHP.
- Todo el código en inglés: variables, métodos, funciones, clases, campos de
  BD, rutas y nombres de test. El español se limita a los textos visibles de
  las vistas Blade y a `lang/es/`. La documentación (`docs/`, `specs/`) también
  en español.
- Validación de entradas con Form Requests.
- URLs públicas de una lista por `slug`, nunca por `id`.
- Interfaz mobile-first. Un solo tema por ahora; modo oscuro es mejora futura.

## Archivos / módulos clave

- `app/Models/ShoppingList.php`, `app/Models/Item.php`
- `app/Http/Controllers/Api/ShoppingListController.php`, `.../ItemController.php`
- `routes/api.php`
- `public/manifest.json`, `public/sw.js`

## Reglas

- Lee `docs/constitution.md` y la spec activa en `specs/` antes de tocar código.
- La constitución manda: si una feature choca con ella, se replantea la feature.
- Nuevas convenciones/arquitectura se documentan aquí (`AGENTS.md`), no en
  `CLAUDE.md`.
- No modifiques archivos dentro de `specs/` salvo petición explícita.
- No subas `.env`. No asumas colas ni schedulers sin verificar el plan de hosting.
- No añadas WebSockets/Reverb ni autenticación sin cambiar antes la constitución.

## Al terminar cualquier tarea

- Ejecuta `php artisan test` y confirma en la respuesta que pasa.
- Ejecuta `php artisan pint` sobre los archivos tocados.
