# Tareas — Spec 001

Tareas de <30 min, ordenadas por dependencia. Cada una: sus RF, un checkbox y
una línea "Hecho cuando:" verificable. Regeneradas tras la 2ª ronda de
clarificación (contador de versión, slug base64url, borrado físico, T0).

## Saneamiento

- [x] T0. Sanear el esqueleto de Laravel: borrar `app/Models/User.php`, las
      migraciones `create_users_table` y `create_personal_access_tokens_table`,
      `create_jobs_table`; quitar `laravel/sanctum` de `composer.json`
      (`composer remove`) y la ruta `GET /user` de `routes/api.php`; instalar
      Pest 3.8 (`pestphp/pest:^3.8`, `pestphp/pest-plugin-laravel`) y convertir
      `tests/` — los tests de navegador van con Playwright directo
      (`npx playwright test`), no con `pest-plugin-browser`, porque el entorno
      usa PHP 8.2 y Pest 4 exige 8.3+; añadir Alpine (`alpinejs`, versión pineada) a
      `package.json` y a `input` de `vite.config.js` junto a
      `resources/js/list.js`; poner `.env.example` en `DB_CONNECTION=mysql` con
      marcadores + `SESSION_DRIVER=cookie`; fijar `APP_LOCALE=es` y
      `APP_FALLBACK_LOCALE=es`; publicar `lang/es/validation.php`. (RF-30)
      Hecho cuando: al migrar no se crean `users`/`sessions`/
      `personal_access_tokens`/`jobs`; `grep -r sanctum composer.json` vacío;
      no hay ruta `user` en `php artisan route:list`; `php artisan test` corre
      con Pest 3; `npm run build` compila `list.js`; `config('app.locale')==='es'`;
      `lang/es/validation.php` existe.

## Datos y modelos

- [ ] T1. Migración `create_shopping_lists_table`: `id`, `slug` string(22)
      unique, `name` string(60), `version` unsignedBigInteger default 0,
      `timestamps()`. (RF-1, RF-7, RF-24)
      Hecho cuando: `php artisan migrate` crea la tabla y un test
      `Schema::hasColumns` verifica `slug`, `name`, `version` y el índice unique
      de `slug`.
- [ ] T2. Migración `create_items_table`: `id`, `shopping_list_id` FK
      `cascadeOnDelete`, `name` string(100), `quantity` string(50) null,
      `added_by` string(50) null, `is_purchased` bool default false, `version`
      unsignedBigInteger, `timestamps()`, `softDeletes()`, índices
      `(shopping_list_id, version)` y `(shopping_list_id, is_purchased, created_at)`.
      (RF-10, RF-11, RF-12, RF-16, RF-24)
      Hecho cuando: `php artisan migrate` corre sin error y un test de esquema
      verifica columnas, los dos índices y `deleted_at`.
- [ ] T3. Modelo `ShoppingList`: `hasMany(Item)`, `getRouteKeyName()='slug'`,
      `$fillable=['name']`, evento `creating` que asigna slug único
      (`random_bytes(16)` → base64url sin relleno) con reintento en colisión;
      método `bumpVersion()` que hace `increment('version')` y devuelve el nuevo
      valor. (RF-1, RF-3, RF-24)
      Hecho cuando: test — dos listas → slugs distintos, 22 chars del alfabeto
      `[A-Za-z0-9_-]`, no numéricos; mockeando `random_bytes` para colisionar una
      vez, la segunda lista igual obtiene slug libre; `bumpVersion()` incrementa.
- [ ] T4. Modelo `Item`: `belongsTo(ShoppingList)`, `use SoftDeletes`,
      `$fillable=['name','quantity','added_by','is_purchased']`, cast
      `is_purchased => bool`, mutadores de `quantity`/`added_by` que hacen `trim`
      y devuelven `null` si queda vacío. (RF-10, RF-11, RF-12, RF-15, RF-16, RF-17)
      Hecho cuando: test — dos ítems con el mismo nombre coexisten; `quantity`
      `"  "` se guarda como `null`; `delete()` deja `deleted_at` y el ítem sale
      de la relación por defecto.
- [ ] T5. Factories `ShoppingListFactory` e `ItemFactory`. (RF: —)
      Hecho cuando: `ShoppingList::factory()->has(Item::factory()->count(3))->create()`
      funciona en un test.
- [ ] T6. `ItemResource`: `{id, name, quantity, added_by, is_purchased, version}`;
      nunca `shopping_list_id` ni campos de lápida. (RF-3, RF-24, RF-32)
      Hecho cuando: test — `ItemResource::make($item)->toArray()` tiene
      exactamente esas 6 claves.

## Contador de versión y transacciones

- [ ] T7. Servicio/trait de escritura versionada: helper que, dentro de una
      transacción, hace `lockForUpdate` de la fila de lista, incrementa
      `version`, aplica el callback y sella el `version` de las filas tocadas.
      (RF-24, RF-25)
      Hecho cuando: test — dos llamadas secuenciales producen `version` 1 y 2 en
      la lista; la fila de ítem tocada queda sellada con el `version` de su
      escritura.

## Validación (Form Requests)

- [ ] T8. `StoreListRequest` / `UpdateListRequest`: `name` requerido, string,
      `trim`, 1–60 chars; mensajes en español donde el default no baste. (RF-2,
      RF-7)
      Hecho cuando: test unitario — nombre vacío, solo espacios y de 61 chars
      fallan; "Feria" pasa; el mensaje sale en español.
- [ ] T9. `StoreItemRequest`: `name` req. 1–100 (trim); `quantity` nullable ≤50;
      `added_by` nullable ≤50; `prepareForValidation` recorta y convierte
      `""`→`null` en `quantity`/`added_by`. (RF-11, RF-12, RF-13)
      Hecho cuando: test — nombre `""`/>100 y `quantity`/`added_by` >50 fallan;
      `quantity`/`added_by` con solo espacios llegan al controlador como `null`.
- [ ] T10. `UpdateItemRequest`: `name` opcional 1–100, `quantity` opcional ≤50
      (mismo trim→null), `is_purchased` opcional boolean. (RF-14, RF-15)
      Hecho cuando: test — `{is_purchased:true}` solo pasa; `{name:""}` falla.

## API — Controladores

- [ ] T11. `ShoppingListController@store`. (RF-1, RF-2)
      Hecho cuando: test — `POST /api/lists {name}` → 201 con `{slug,name,url}`,
      `url` absoluta y arranca por `config('app.url')`; nombre inválido → 422.
- [ ] T12. `ShoppingListController@show` con `ItemResource`: devuelve
      `{slug, name, version, items[]}` con ítems ordenados por el servidor (no
      comprados primero, luego comprados; cada grupo por `created_at` asc). (RF-3,
      RF-4, RF-18)
      Hecho cuando: test — con ítems comprados y no comprados en orden mezclado,
      la respuesta trae los no comprados primero y cada grupo por creación asc;
      incluye `version`; slug inexistente → 404.
- [ ] T13. `ShoppingListController@update` (renombra vía T7, conserva slug,
      incrementa `version`). (RF-7, RF-25)
      Hecho cuando: test — `PATCH` cambia `name`, `slug` intacto, `version` +1;
      dos `PATCH` de nombre seguidos → gana el último, `version` +2; 404 si no
      existe.
- [ ] T14. `ShoppingListController@destroy`: borrado **físico** de la lista y de
      todos sus ítems (activos y lápidas). (RF-4, RF-8, RF-9)
      Hecho cuando: test — `DELETE` → 204; no queda ninguna fila en
      `shopping_lists` ni `items` (incl. `withTrashed`) para esa lista; acceso
      posterior al slug → 404 con cuerpo y headers idénticos al de un slug que
      nunca existió.
- [ ] T15. `ItemController@store` (usa T7: lock de lista, tope de 200 en el
      controlador, sella `version`). (RF-10, RF-11, RF-12, RF-13, RF-17, RF-20,
      RF-32)
      Hecho cuando: test — 201 con el ítem (`is_purchased=false`, forma de
      `ItemResource`); mismo nombre dos veces coexiste; con 199 sembrados el 200
      entra, con 200 sembrados → 422 con mensaje de límite en español; un `name`
      con `<script>` se guarda literal.
- [ ] T16. `ItemController@update` (T7; `->fill()` solo con campos presentes;
      marcado sin confirmación). (RF-14, RF-15, RF-25)
      Hecho cuando: test — `PATCH {is_purchased:true}` → 200 y persiste; dos
      `PATCH` seguidos al mismo ítem, uno `{name}` y otro `{is_purchased:true}`,
      dejan ambos cambios; `PATCH /api/lists/A/items/{id-de-B}` → 404.
- [ ] T17. `ItemController@destroy` (soft delete vía T7, sella `version`). (RF-16)
      Hecho cuando: test — `DELETE` → 204, fila con `deleted_at` y `version`
      sellado, ausente en `show`.
- [ ] T18. `ItemController@purgePurchased`
      (`POST /api/lists/{slug}/items/purge-purchased`, vía T7; evalúa "comprado"
      contra la BD). (RF-19)
      Hecho cuando: test — solo los comprados quedan con `deleted_at`, responde
      `deleted_ids`, los no comprados intactos; sin comprados → `200` con
      `deleted_ids: []` y `version` sin cambiar.
- [ ] T19. `ItemController@sync`
      (`GET /api/lists/{slug}/items?cursor=`): valida `cursor` (entero, 0..
      `version`); delta con `version > cursor` (`items` activos +
      `deleted_ids`), o carga completa (solo activos, `deleted_ids: []`) si falta
      / no entero / > `version`; devuelve `cursor = version`. (RF-18, RF-22,
      RF-24, RF-27)
      Hecho cuando: test — tras modificar un ítem y borrar otro, `cursor` = la
      `version` previa devuelve el modificado en `items` y el borrado en
      `deleted_ids` (solo `id`); segunda llamada con el nuevo cursor →
      `items: []`, `deleted_ids: []`; sobre una lista eliminada → 404 (RF-27).
- [ ] T20. Casos límite de `sync`: `cursor` ausente / no entero / > `version` →
      carga completa; `cursor` de otra lista → carga completa o delta inocuo;
      ítem creado y borrado entre dos cursores → solo en `deleted_ids`; el corte
      no usa ningún reloj. (RF-24)
      Hecho cuando: test parametrizado cubre los cinco casos.

## Rutas y middleware

- [ ] T21. `routes/api.php`: grupo sin auth con las 9 rutas del contrato; ítems
      anidados con `->scopeBindings()`; sin ninguna ruta que enumere listas.
      (RF-5, RF-30, RF-31)
      Hecho cuando: `php artisan route:list` muestra las 9 rutas, ninguna con
      `auth`, ninguna `GET /api/lists` de índice; test — 3 peticiones al mismo
      slug sin sesión → 3×200.
- [ ] T22. Rate limiting: definir limitadores `lists-create` (10/h),
      `writes` (120/min), `sync` (60/min) por IP en `bootstrap/app.php` y
      aplicarlos con `throttle:` a los grupos de rutas. (RNF límite de peticiones)
      Hecho cuando: test — la 11ª `POST /api/lists` desde la misma IP en 1 h →
      429; `sync` 61/min → 429; una escritura 121/min → 429.
- [ ] T23. Middleware `NoIndex` (`X-Robots-Tag: noindex, nofollow`) en las rutas
      web de lista + `public/robots.txt` con `Disallow: /l/`. (RNF no indexable)
      Hecho cuando: test — `GET /l/{slug}` trae el header; `GET /robots.txt`
      contiene `Disallow: /l/`.

## Frontend — Vistas

- [ ] T24. `resources/views/layout.blade.php`: layout mobile-first compartido,
      `<meta>` de viewport y `theme-color`, `<link rel="manifest">`, registro del
      service worker, `@vite(['resources/js/list.js', ...])`. (RF-28)
      Hecho cuando: `GET /` → 200 e incluye el `<link rel="manifest">` y el
      script de registro del SW.
- [ ] T25. `routes/web.php` + `home.blade.php`: `GET /` con form de crear lista y
      sección "mis listas" (poblada por JS desde `localStorage`). (RF-1, RF-6)
      Hecho cuando: `GET /` → 200 y el HTML contiene el formulario de creación.
- [ ] T26. `list.blade.php` mobile-first: cabecera con nombre editable, acciones
      renombrar / eliminar lista (diálogo de confirmación), input de alta, lista
      de ítems (comprados tachados y al final), botón "limpiar comprados" con
      confirmación; todo el contenido de usuario con `{{ }}`. (RF-3, RF-9, RF-18,
      RF-19, RF-32)
      Hecho cuando: `GET /l/{slug}` → 200 con esos elementos en el DOM; slug
      inexistente → 404; un ítem con `<script>` en el nombre aparece escapado en
      el HTML.
- [ ] T27. `resources/views/offline.blade.php` + `GET /offline`: página offline
      mínima estática. (RF-29)
      Hecho cuando: test — `GET /offline` → 200 con un mensaje de "sin conexión".

## Frontend — Alpine

- [ ] T28. `list.js` — núcleo: carga inicial vía `show`; render reactivo con
      Alpine usando `x-text` para todo el contenido de usuario; alta / edición /
      marcado / borrado que esperan la respuesta de la API antes de tocar la
      vista (sin UI optimista); en cada edición envía **solo los campos que
      cambian**. (RF-3, RF-15, RF-18, RF-25, RF-32)
      Hecho cuando: `npm run build` compila; test Playwright (`npx playwright test`) — alta/marcado/
      borrado se reflejan en la UI tras responder la API; al editar solo el
      nombre el `PATCH` lleva solo `{name}`; un `name` con `<img onerror>` se
      muestra como texto.
- [ ] T29. `list.js` — memoria local: recuerda el enlace abierto y el nombre de
      "quién agrega" en `localStorage`; acción "quitar de mis listas"; poda al
      recibir 404; refresca el nombre guardado tras abrir con éxito; tope de 20.
      (RF-6, RF-21)
      Hecho cuando: test Playwright (`npx playwright test`) — abrir `/l/{slug}` crea la entrada;
      "quitar" la borra; abrir un slug 404 la poda; renombrar refresca el nombre;
      la 21ª lista descarta la más antigua; el nombre de "quién agrega" se
      propone editable.
- [ ] T30. `list.js` — polling: cada 3–4 s llama a `sync` con el último `cursor`,
      fusiona `items` por `id`, quita `deleted_ids`, recoloca por la regla de
      orden de RF-18; pausa con `visibilitychange` y reanuda con consulta
      inmediata al volver al foco; si `sync` → 404 muestra "esta lista ya no
      existe". (RF-18, RF-22, RF-23, RF-27)
      Hecho cuando: test Playwright (`npx playwright test`) — un cambio hecho por petición directa
      aparece en la vista en ≤5 s; ocultar la pestaña detiene el polling y
      mostrarla dispara una consulta inmediata; borrar la lista por otra vía →
      aviso "ya no existe".
- [ ] T31. `list.js` — desconexión: mantiene visible la última lectura conocida;
      las escrituras sin conexión fallan con aviso y NO se encolan; al volver la
      red el polling recupera el estado. (RF-26)
      Hecho cuando: test Playwright (`npx playwright test`) — con red simulada caída, la lista sigue
      visible, una escritura muestra aviso y no cambia nada; al restaurar la red
      el polling vuelve a sincronizar.

## PWA

- [ ] T32. `public/icons/icon-192.png` y `icon-512.png` (iconos reales) +
      `public/manifest.json` (`name`, `short_name`, `icons` 192 y 512,
      `theme_color`, `background_color`, `display:standalone`, `start_url:"/"`).
      (RF-28)
      Hecho cuando: test — `GET /manifest.json` → 200 con mime correcto y las
      claves citadas; `GET /icons/icon-192.png` y `/icons/icon-512.png` → 200 con
      `Content-Type: image/png`.
- [ ] T33. `public/sw.js`: precache del app shell (layout, JS/CSS compilados,
      iconos, `/offline`); cache-first para estáticos, network-only para
      `/api/*`, fallback a `/offline` sin shell ni red; registro en el layout.
      (RF-26, RF-29)
      Hecho cuando: test — `GET /sw.js` → 200 con `Content-Type` de JS y
      `/offline` precacheada responde 200; verificación manual: Lighthouse
      "installable", shell offline y página offline en primer arranque sin red.

## Mantenimiento

- [ ] T34. Comando `php artisan items:purge-tombstones --before=<fecha>`:
      `forceDelete` de ítems `onlyTrashed` con `deleted_at` anterior a la fecha;
      aborta sin `--before`; reporta el conteo. (RF-16)
      Hecho cuando: test — con lápidas viejas y recientes, el comando borra solo
      las anteriores a `--before` y reporta el número; sin `--before` sale con
      error.

## Cierre de la feature

_Checkboxes sueltos, fuera de la numeración `Tn` — no se implementan con
`/sdd:implement`._

- [ ] Suite completa verde: `php artisan test` y `npx playwright test` sin
      fallos, y `php artisan pint --test` sin cambios.
- [ ] Verificación de todos los RF: `/sdd:validate` →
      `specs/001-lista-compras-familiar/validation.md` (todos los RF cubiertos o
      justificados).
- [ ] `docs/deploy.md`: despliegue subiendo archivos por `rsync`/`scp` sobre la
      conexión SSH del runner (sin `git clone`/`git pull` en el servidor),
      **excluyendo `docs/` y `specs/`**, `composer install --no-dev`, `.env` con
      MySQL de Hostinger, `CACHE_STORE=database`, `migrate --force`,
      `npm run build`, HTTPS forzado, permisos de `storage/`, nota de
      `items:purge-tombstones` manual. (RNF HTTPS, RF-16)
- [ ] Mover la feature 001 a "Hecho ✅" en `docs/roadmap.md`, enlazando la
      carpeta (lo hace `/sdd:validate` al cerrar).
- [ ] Convención nueva descubierta durante la implementación → anotarla en
      `AGENTS.md`.

## Cobertura RF → tarea

| RF                       | Tareas                       |
| ------------------------ | ---------------------------- |
| RF-1                     | T1, T3, T11, T25             |
| RF-2                     | T8, T11                      |
| RF-3                     | T3, T12, T26, T28            |
| RF-4                     | T12, T14, T19                |
| RF-5                     | T21                          |
| RF-6                     | T25, T29                     |
| RF-7                     | T1, T8, T13                  |
| RF-8                     | T14                          |
| RF-9                     | T14, T26                     |
| RF-10                    | T2, T4, T15                  |
| RF-11                    | T2, T4, T9, T15              |
| RF-12                    | T2, T4, T9, T15              |
| RF-13                    | T9, T15                      |
| RF-14                    | T10, T16                     |
| RF-15                    | T4, T10, T16, T28            |
| RF-16                    | T2, T4, T17, T34             |
| RF-17                    | T4, T15                      |
| RF-18                    | T12, T19, T26, T28, T30      |
| RF-19                    | T18, T26                     |
| RF-20                    | T15                          |
| RF-21                    | T29                          |
| RF-22                    | T19, T30, T31                |
| RF-23                    | T30                          |
| RF-24                    | T1, T2, T3, T6, T7, T19, T20 |
| RF-25                    | T7, T13, T16, T28            |
| RF-26                    | T31, T33                     |
| RF-27                    | T14, T19, T30                |
| RF-28                    | T24, T32                     |
| RF-29                    | T27, T33                     |
| RF-30                    | T0, T21                      |
| RF-31                    | T21                          |
| RF-32                    | T6, T15, T26, T28            |
| RNF idioma               | T0, T8, T15                  |
| RNF slug no adivinable   | T1, T3                       |
| RNF límite de peticiones | T22                          |
| RNF no indexable         | T23                          |
| RNF HTTPS                | Cierre (docs/deploy.md)      |
