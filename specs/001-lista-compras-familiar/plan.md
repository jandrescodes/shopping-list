# Plan técnico — Spec 001

> Regenerado tras la 2ª ronda de clarificación
> (ver `spec.md` § Clarificaciones — "Fase 3 — segunda ronda").
> El corte de sincronización pasa de `updated_at`/`timestamp(3)` a un **contador
> de versión monótono por lista**; el slug pasa a 16 bytes base64url; el borrado
> de lista es físico; se añade una tarea T0 de saneamiento del esqueleto.

## Estado de partida del repo (a sanear en T0)

- `app/Models/User.php` y migraciones `create_users_table`,
  `create_personal_access_tokens_table` presentes; `laravel/sanctum` en
  `composer.json`; `routes/api.php` con `GET /user` bajo `auth:sanctum`. Todo
  esto viola la constitución 4 y RF-30 → se elimina antes de T1.
- `create_cache_table` y `create_jobs_table` presentes. `cache` se conserva
  (rate limiting por IP necesita un store; se usa `database` o el default del
  hosting). `jobs` se elimina: no hay colas (constitución 5).
- `phpunit.xml` ya fija `DB_CONNECTION=sqlite` / `:memory:`. Se mantiene.
- `package.json` trae Vite + Tailwind 4 + axios. Falta Alpine y el input de Vite
  para `list.js`.
- `.env.example` en SQLite y `SESSION_DRIVER=database` → se corrige a MySQL y
  `cookie`.

## Estructura de módulos

| Módulo                                                             | Responsabilidad                                                                                         | RF                                                           |
| ------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------ |
| `app/Models/ShoppingList.php`                                      | Lista; `hasMany(Item)`; `getRouteKeyName()='slug'`; genera slug al crear; `bumpVersion()` atómico       | RF-1, RF-3, RF-7, RF-8, RF-24                                |
| `app/Models/Item.php`                                              | Ítem; `belongsTo(ShoppingList)`; `SoftDeletes`; cast `is_purchased:bool`; sello `version`               | RF-10..RF-20, RF-24                                          |
| `app/Http/Controllers/Api/ShoppingListController.php`              | `store`, `show`, `update`, `destroy`                                                                    | RF-1..RF-9, RF-30, RF-31                                     |
| `app/Http/Controllers/Api/ItemController.php`                      | `store`, `update`, `destroy`, `sync`, `purgePurchased`                                                  | RF-10..RF-27                                                 |
| `app/Http/Requests/StoreListRequest.php` / `UpdateListRequest.php` | Validación nombre de lista (RF-2)                                                                       | RF-2, RF-7                                                   |
| `app/Http/Requests/StoreItemRequest.php` / `UpdateItemRequest.php` | Validación de ítem; el tope de 200 se evalúa en el controlador (ver algoritmo 3)                        | RF-11..RF-14                                                 |
| `app/Http/Resources/ItemResource.php`                              | Forma JSON estable de un ítem; nunca incluye `id` de lista ni datos de lápida                           | RF-3, RF-24, RF-32                                           |
| `app/Support/ListVersion.php` (o método en el modelo)              | Incremento atómico del contador y sellado de filas dentro de la transacción                             | RF-24, RF-25                                                 |
| `app/Console/Commands/PurgeTombstones.php`                         | `items:purge-tombstones --before=<fecha>`: borra físicamente ítems con `deleted_at` anterior a la fecha | RF-16                                                        |
| `app/Http/Middleware/NoIndex.php`                                  | Añade `X-Robots-Tag: noindex, nofollow` a las vistas de lista                                           | RNF no indexable                                             |
| `routes/api.php`                                                   | Endpoints REST bajo `/api`, sin middleware de auth, con `throttle` por grupo                            | RF-30, RF-31, RNF límite de peticiones                       |
| `routes/web.php`                                                   | `GET /` (crear/recordadas) y `GET /l/{slug}` (vista de lista)                                           | RF-3, RF-6                                                   |
| `routes/console.php` o `bootstrap/app.php`                         | Registro del comando de purga (sin schedule)                                                            | RF-16                                                        |
| `resources/views/layout.blade.php`                                 | Layout mobile-first compartido; registra el service worker; `<meta>` PWA; `<nav>` "Mis listas" al home salvo en `/`  | RF-28, RF-33                                        |
| `resources/views/home.blade.php`                                   | Formulario de creación + accesos a listas recordadas                                                    | RF-1, RF-6                                                   |
| `resources/views/list.blade.php`                                   | Vista de una lista; monta Alpine; escapa todo con `{{ }}`; botón "Compartir"                            | RF-3, RF-15, RF-18, RF-32, RF-33, RF-34                      |
| `resources/views/offline.blade.php`                                | Página offline mínima estática                                                                          | RF-29                                                        |
| `resources/js/list.js` (Alpine)                                    | Render, alta/edición/marcado/borrado, polling, memoria local, avisos sin conexión, compartir enlace     | RF-6, RF-15, RF-19, RF-21, RF-22, RF-25, RF-26, RF-27, RF-32, RF-34 |
| `public/manifest.json`                                             | Manifest PWA (nombre, iconos 192/512, `theme_color`, `display:standalone`)                              | RF-28                                                        |
| `public/icons/icon-192.png`, `icon-512.png`                        | Ficheros de icono reales                                                                                | RF-28                                                        |
| `public/sw.js` + registro en layout                                | App shell cache-first; `/api/*` siempre a red; sirve `offline` sin caché                                | RF-26, RF-29                                                 |
| `public/robots.txt`                                                | `Disallow: /l/`                                                                                         | RNF no indexable                                             |
| `lang/es/validation.php`                                           | Traducciones de validación por defecto en español                                                       | constitución 8, RNF idioma                                   |

## Modelo de datos

Motor: MySQL en producción (constitución 6); SQLite `:memory:` en tests (ya en
`phpunit.xml`). **El corte de sync no usa fechas ni SQL de motor**: es un entero
gestionado por el servidor, portable a SQLite sin cambios.

### `shopping_lists`

| Columna                     | Tipo                              | Notas                                                                                 |
| --------------------------- | --------------------------------- | ------------------------------------------------------------------------------------- |
| `id`                        | `bigIncrements`                   | interno; nunca en URLs ni respuestas                                                  |
| `slug`                      | `string(22)`, `unique`            | 16 bytes CSPRNG → base64url sin relleno; ver algoritmo 1                              |
| `name`                      | `string(60)`                      | no vacío tras `trim` (RF-2)                                                           |
| `version`                   | `unsignedBigInteger`, default `0` | contador monótono; se incrementa en cada escritura sobre la lista o sus ítems (RF-24) |
| `created_at` / `updated_at` | `timestamp`                       | precisión por defecto; informativos, **no** son el cursor                             |

### `items`

| Columna                     | Tipo                                  | Notas                                                                              |
| --------------------------- | ------------------------------------- | ---------------------------------------------------------------------------------- |
| `id`                        | `bigIncrements`                       | expuesto en la API, siempre scoped a la lista de la ruta                           |
| `shopping_list_id`          | `foreignId`, `cascadeOnDelete`, index |                                                                                    |
| `name`                      | `string(100)`                         | texto plano (RF-32)                                                                |
| `quantity`                  | `string(50)`, nullable                | texto libre; vacío tras `trim` ⇒ `null` (RF-11)                                    |
| `added_by`                  | `string(50)`, nullable                | texto libre, sin FK; vacío tras `trim` ⇒ `null` (RF-12)                            |
| `is_purchased`              | `boolean`, default `false`            |                                                                                    |
| `version`                   | `unsignedBigInteger`, default `0`, index | sello: valor del contador de la lista tras la escritura que tocó este ítem (RF-24); `0` es transitorio hasta que la escritura versionada (T7) lo fija |
| `created_at` / `updated_at` | `timestamp`                           | `created_at` fija el orden intra-grupo (RF-18)                                     |
| `deleted_at`                | `timestamp`, nullable                 | `SoftDeletes`; lápida para sync (RF-16)                                            |

Índices: `items(shopping_list_id, version)` para la consulta de sync;
`items(shopping_list_id, is_purchased, created_at)` para el orden de la carga
completa.

**Invariantes**

- `name` de lista e ítem: no vacío tras `trim`, dentro del límite (RF-2, RF-13).
- `quantity` y `added_by`: `trim` de espacios exteriores; vacío ⇒ `null`.
- Ítems activos por lista (`deleted_at IS NULL`) ≤ 200 (RF-20, cota blanda:
  dos altas concurrentes pueden dejar 201, aceptado).
- `list.version` es no decreciente. Toda fila (ítem activo o lápida) lleva el
  `version` de la escritura que la produjo.
- `slug` no derivable de `id`, no ordenable, no enumerable (RF-5, RNF).

## Algoritmos / lógica no trivial

### 1. Generación de slug (RF-1, RNF "Slug no adivinable")

```
do {
    $slug = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
} while (ShoppingList::where('slug', $slug)->exists());
```

16 bytes de `random_bytes` (CSPRNG) = 128 bits exactos → 22 caracteres
`[A-Za-z0-9_-]`, sensible a mayúsculas. El bucle cubre la colisión teórica
(caso límite de la spec). La ruta es `/l/{slug}` con match exacto: el route model
binding resuelve por la columna `slug` sin normalizar; un slug con mayúsculas
cambiadas o puntuación pegada da 404 (RF-4).

**Descartado**: `Str::random(40)` (entropía menos explícita, 40 chars), hex
(44 chars para 128 bits), ULID/UUIDv4 (ordenable o <128 bits, RNF lo prohíbe).

### 2. Contador de versión y sincronización (RF-22, RF-24, RF-25, RF-27)

**Escritura** (alta, edición, marca/desmarca, borrado lógico de ítem, "limpiar
comprados", renombrado de lista) — todo dentro de una transacción:

1. `SELECT ... FOR UPDATE` sobre la fila de `shopping_lists` (lock pesimista de
   fila; serializa las escrituras concurrentes sobre la misma lista, RF-25).
2. `version = version + 1` en la lista.
3. Aplicar el cambio a la(s) fila(s) de `items` afectada(s), sellando su columna
   `version` con el nuevo valor. En el `PATCH` de ítem se hace `->fill()` solo
   con los campos presentes en la petición (campo por campo, RF-25).
4. `COMMIT`. El orden de commit de MySQL define "última escritura gana".

Sobre SQLite `:memory:` no hay `FOR UPDATE` real, pero los tests corren en un
solo proceso y `RefreshDatabase` serializa; la corrección lógica se verifica con
dos peticiones secuenciales.

**Lectura incremental** — `GET /api/lists/{slug}/items?cursor=<int|ausente>`:

1. Route binding resuelve la lista por `slug` → 404 si no existe (RF-27; el
   cuerpo del 404 es idéntico al de un slug que nunca existió, RF-4).
2. Validar `cursor`: entero ≥ 0. Si **falta, no es entero, o es > `list.version`**
   ⇒ _carga completa_: `items` = ítems activos ordenados por RF-18,
   `deleted_ids: []`, `cursor: list.version`. (Un cursor "de otra lista" cae aquí
   por ser > o < de forma incoherente; en el peor caso devuelve un delta inocuo
   que el cliente fusiona por `id`.)
3. Si `cursor` es válido (`0 <= cursor <= list.version`) ⇒ _delta_:
    - `items` = `Item::where(list)->where('version', '>', $cursor)->whereNull('deleted_at')` con la forma de `ItemResource`.
    - `deleted_ids` = `Item::withTrashed()->where(list)->where('version', '>', $cursor)->whereNotNull('deleted_at')->pluck('id')`.
    - `cursor` = `list.version` (valor actual).
4. Respuesta: `{ items, deleted_ids, cursor }`. "Sin novedades" ⇒ `items: []`,
   `deleted_ids: []`, mismo `cursor`.

`>` estricto: el contador da un valor distinto a cada escritura, no hay empates,
no hace falta `>=`. El cliente fusiona por `id` (rerecibir un ítem es idempotente).

**Descartado**: corte por `updated_at` + `timestamp(3)` — `current_timestamp(3)`
no existe en SQLite (rompe los tests), hay desfase de timezone BD↔Eloquent, y
sin `$dateFormat` con milisegundos la ventana real es de 1 s. El contador entero
elimina las tres cosas de raíz.

### 3. Tope de ítems (RF-20)

En `ItemController::store`, dentro de la transacción y tras el lock de la lista:
`if ($list->items()->count() >= 200) abort(422, 'La lista alcanzó el límite de 200 ítems.')`.
Se evalúa en el controlador (no en el Form Request) porque necesita la lista ya
resuelta por route binding y el lock de la transacción. Dos altas concurrentes
pueden dejar 201; aceptado (cota de protección, RF-20). La sincronización nunca
crea ítems, así que no evalúa el tope.

### 4. Última escritura gana, campo por campo (RF-25, RF-7)

El `PATCH` de ítem y el de lista hacen `->fill($request->validated())` solo con
las claves presentes en el cuerpo y `->save()`. Serializado por el lock de la
fila de lista (algoritmo 2). Si A renombra la lista y B marca un ítem casi a la
vez, ambos cambios sobreviven porque tocan campos/filas distintos y cada
transacción incrementa `version`. El cliente (`list.js`) **envía solo los campos
que cambian**, nunca el objeto completo (test Playwright lo verifica).

### 5. Limpiar comprados (RF-19)

Dentro de la transacción con lock de lista:
`$purchased = $list->items()->where('is_purchased', true)->get();`
`$list->increment('version'); $purchased->each(fn($i) => $i->forceFill(['version' => $list->version])->delete());`
Devuelve `deleted_ids`. Evalúa "comprado" contra la BD en ese instante, no según
el cliente. Sin comprados ⇒ no incrementa nada y responde `200 {deleted_ids: []}`.

### 6. Borrado físico de lista (RF-4, RF-8)

`DELETE /api/lists/{slug}` → `$list->items()->withTrashed()->forceDelete(); $list->forceDelete();`
(o `cascadeOnDelete` de la FK + `forceDelete` de la lista). No queda ninguna fila.
El 404 posterior es idéntico byte a byte al de un slug inexistente.

### 7. Purga manual de lápidas (RF-16)

`php artisan items:purge-tombstones --before=2026-01-01`
→ `Item::onlyTrashed()->where('deleted_at', '<', $before)->forceDelete()` y
reporta el número de filas. Sin `--before` aborta pidiéndolo explícitamente.
Sin scheduler ni cron (constitución 5). Documentado en `docs/deploy.md`.

## Decisiones técnicas

- **Contador de versión entero por lista** vs corte por `updated_at`/`timestamp(3)`
  → portable a SQLite, sin timezone, sin truncado a segundos; el cursor es opaco
  para el cliente igual que antes. Coste: una columna y un `increment` por
  escritura, despreciable.
- **Lock pesimista (`lockForUpdate`) de la fila de lista** vs versión optimista
  con reintento → el volumen es doméstico (pocas escrituras concurrentes reales);
  el lock serializa de forma trivial y determinista y da el "orden de llegada"
  que pide RF-25. Optimista añadiría reintentos y complejidad sin beneficio.
- **Slug = 16 bytes base64url (22 chars)** vs `Str::random(40)` / hex / ULID →
  128 bits exactos, URL-safe, corto, no ordenable, no derivable del `id` (RNF).
- **Borrado físico de lista** vs soft delete + tombstone de lista → no hay sync a
  nivel de "lista existe/no existe" (el cliente lo deduce del 404, RF-27); una
  lista borrada no necesita propagarse por delta. Menos estado muerto.
- **Alpine.js** vs JS vanilla → la vista de lista tiene binding bidireccional
  (edición inline, toggle de comprado, render reactivo del delta de polling);
  con vanilla habría que reimplementar reactividad y plantillas a mano. Alpine
  son ~15 KB, sin build step propio (se empaqueta con Vite), encaja con Blade y
  con "sin SPA". Se pinea la versión en `package.json` y se añade al input de
  Vite junto a `resources/js/list.js`. Cumple constitución 1 (dependencia de
  runtime justificada).
- **Pest 3.8 (API) + Playwright directo (cliente) como dependencias de
  desarrollo** vs solo demo manual → RF-6/21/22/23/26/27 son lógica de cliente y
  la constitución 3 exige tests en verde por tarea. El entorno usa PHP 8.2
  (constitución 1) y Pest 4 + `pest-plugin-browser` exigen PHP 8.3+; por eso la
  API va con **Pest 3.8** (`php artisan test`) y los tests de navegador con
  **Playwright directo** (`npx playwright test`, CLI o MCP), fuera de la suite de
  Pest. Ninguno toca el runtime ni el hosting compartido. La constitución 3 se
  cumple para la API; los RF de cliente quedan cubiertos por un test de navegador
  automatizado que hace de puerta. No se enmienda la constitución.
- **Rate limiting por IP con `throttle`** (constitución 5, RNF) → tres limitadores
  nombrados en `bootstrap/app.php`: `lists-create` (10/h), `writes` (120/min),
  `sync` (60/min). Store: `cache` (database driver en el hosting).
- **Escapado**: Blade `{{ }}` en las vistas, `x-text`/`textContent` en Alpine;
  nunca `{!! !!}` ni `x-html` para contenido de usuario (RF-32).
- **`X-Robots-Tag` vía middleware + `robots.txt`** vs solo `robots.txt` → un slug
  pegado en un sitio público podría indexarse igual; el header lo impide en la
  respuesta.
- **SQLite `:memory:` en tests** (ya configurado) vs MySQL en tests → aislamiento
  por proceso y velocidad; el diseño ya no depende de features de MySQL.
- **`GET /l/{slug}` responde 404 directo** si el slug no existe (no 200 con HTML
  y "no encontrado" en JS) → coherente con RF-4; menos superficie. Se recoge en
  la spec vía esta decisión de plan.
- **Navegación al home vía `<nav>` en el layout con `@unless(request()->is('/'))`**
  (RF-33) vs enlace por vista → una sola definición, aparece en toda página menos
  el home; test de contenido Pest. No añade rutas: `/` ya existía.
- **Compartir con `navigator.share` → `navigator.clipboard` → URL en claro**
  (RF-34) vs solo copiar / librería de share → API nativa del navegador, sin
  dependencia (constitución 1); en móvil abre la hoja del SO. Cadena de
  degradación: sin Web Share (p. ej. escritorio) copia al portapapeles y avisa;
  sin Clipboard API (contexto no seguro) muestra la URL seleccionable. Se
  comparte `window.location.href` (lleva el `slug`, RF-5). `AbortError` al
  cancelar la hoja se traga en silencio. 100 % cliente: no toca API, va offline.
  Test de navegador Playwright con `navigator.share`/`clipboard` interceptados.

## Contrato de interfaz

Todas las respuestas JSON. Sin cabeceras de auth. `{slug}` resuelve por `slug`
con match exacto; `404` genérico si no existe (sin distinguir "eliminada").
`cursor` es un entero opaco emitido por el servidor.

| Método y ruta                                  | Cuerpo entrada                      | Éxito                                  | Errores                                 | Throttle       |
| ---------------------------------------------- | ----------------------------------- | -------------------------------------- | --------------------------------------- | -------------- |
| `POST /api/lists`                              | `{name}`                            | `201 {slug, name, url}`                | `422`, `429`                            | `lists-create` |
| `GET /api/lists/{slug}`                        | —                                   | `200 {slug, name, version, items[]}`   | `404`                                   | —              |
| `PATCH /api/lists/{slug}`                      | `{name}`                            | `200 {slug, name, version}`            | `404`, `422`, `429`                     | `writes`       |
| `DELETE /api/lists/{slug}`                     | —                                   | `204`                                  | `404`, `429`                            | `writes`       |
| `GET /api/lists/{slug}/items?cursor=`          | —                                   | `200 {items[], deleted_ids[], cursor}` | `404`, `429`                            | `sync`         |
| `POST /api/lists/{slug}/items`                 | `{name, quantity?, added_by?}`      | `201 {item}`                           | `404`, `422` (incl. lista llena), `429` | `writes`       |
| `PATCH /api/lists/{slug}/items/{id}`           | `{name?, quantity?, is_purchased?}` | `200 {item}`                           | `404`, `422`, `429`                     | `writes`       |
| `DELETE /api/lists/{slug}/items/{id}`          | —                                   | `204`                                  | `404`, `429`                            | `writes`       |
| `POST /api/lists/{slug}/items/purge-purchased` | —                                   | `200 {deleted_ids[]}`                  | `404`, `429`                            | `writes`       |

Forma de `item` (`ItemResource`): `{id, name, quantity, added_by, is_purchased, version}`.
Nunca incluye `shopping_list_id` ni datos de lápidas.

`url` de `POST /api/lists` = `URL::to("/l/{$slug}")` forzando esquema `https` en
producción (`{APP_URL}/l/{slug}`, absoluto — criterio de finalización).

Rutas web: `GET /` (home), `GET /l/{slug}` (vista de lista; `404` directo si el
slug no existe; `X-Robots-Tag: noindex, nofollow`), `GET /offline` (página
offline precacheada por `sw.js`).

## Riesgos

- **Slug filtrado** (compartido en un chat público, indexado, en el historial de
  un navegador prestado) → sin auth, quien lo tenga controla la lista. Mitigación
  parcial: 128 bits de entropía, `X-Robots-Tag` + `robots.txt`, HTTPS
  obligatorio. Se acepta: es la premisa de la constitución 4.
- **`FOR UPDATE` no existe en SQLite `:memory:`** → la serialización real de
  RF-25 no se ejerce en los tests, solo su corrección lógica secuencial.
  Mitigación: verificación manual en dos dispositivos (criterio de finalización).
  Riesgo residual aceptado.
- **Lápidas sin purga automática** (RF-16, constitución 5) → `items` crece de
  forma monótona. Mitigación: `items:purge-tombstones` manual, documentado en
  `docs/deploy.md`.
- **Rate limiting sobre el store `cache`** en hosting compartido → si el driver
  de caché no persiste entre peticiones, los limitadores no cuentan. Mitigación:
  fijar `CACHE_STORE=database` en el `.env` de producción y verificarlo en el
  despliegue.
- **Dos altas concurrentes en el límite de 200** pueden dejar 201 → aceptado
  explícitamente (RF-20, cota de protección, no de negocio).
- **Playwright fuera de `php artisan test`** → una suite puede pasar mientras la
  otra falla si no se ejecutan ambas. Mitigación: la regla de `AGENTS.md`
  § "Al terminar cualquier tarea" y la puerta de verificación de la constitución 3.

## Estrategia de verificación

Pest 3.8, feature tests contra SQLite `:memory:` (`RefreshDatabase`), bajo
`php artisan test`. Tests de navegador con Playwright directo
(`npx playwright test`, CLI o MCP) para la capa de cliente, fuera de la suite de
Pest (Pest 4 / `pest-plugin-browser` no son instalables en PHP 8.2).

- **Saneamiento (T0)**: no existe `App\Models\User`; `php artisan migrate` no
  crea `users`/`sessions`/`personal_access_tokens`/`jobs`; no hay ruta
  `GET /api/user`; `composer.json` sin `laravel/sanctum`; `.env.example` con
  `DB_CONNECTION=mysql` y `SESSION_DRIVER=cookie`; `config('app.locale') === 'es'`;
  `lang/es/validation.php` existe; Alpine en `package.json` y en el input de Vite;
  `npm run build` compila `list.js`.
- **Listas**: `store` feliz + `422` (vacío, >60, solo espacios); `show` con
  ítems devuelve `version`; `update` renombra y conserva slug e incrementa
  `version`; `destroy` → `204`, luego `404`, y **no queda ninguna fila** de la
  lista ni de sus ítems (incl. lápidas); slug inexistente → `404` con cuerpo y
  headers idénticos al de una lista borrada (RF-4).
- **Slug**: 22 caracteres del alfabeto `[A-Za-z0-9_-]`; no numérico ni
  secuencial; dos listas → slugs distintos; colisión forzada
  (`ShoppingList::creating` o mock de `random_bytes`) → reintenta; match
  sensible a mayúsculas (`/l/{slug}` con un char cambiado → `404`).
- **Ítems**: `store` feliz (con y sin `quantity`/`added_by`); `422` nombre
  vacío/>100, `quantity`>50, `added_by`>50; `quantity`/`added_by` solo espacios
  → `null`; `update` nombre y cantidad; `is_purchased` toggle sin confirmación;
  `destroy` → soft delete (fila con `deleted_at`, ausente en `show`); dos ítems
  mismo nombre coexisten; cada escritura incrementa `list.version` y sella el
  `version` del ítem.
- **Orden en `show`** (RF-18): no comprados primero, luego comprados; dentro de
  cada grupo por `created_at` ascendente (sembrar con `created_at` explícitos).
- **Escritura por campo** (RF-25): dos `PATCH` seguidos al mismo ítem, uno
  `{name}` y otro `{is_purchased:true}`, dejan ambos cambios; `version` final =
  el mayor de los dos.
- **Renombrado concurrente de lista** (RF-25/RF-7): dos `PATCH` de nombre
  secuenciales → gana el último; `version` incrementa dos veces.
- **Tope** (RF-20): 199 sembrados → el 200 entra (`201`); con 200 el siguiente
  → `422` con mensaje en español; marcar/editar/borrar siguen `200` con la lista
  llena; `purge-purchased` libera cupo. La sincronización con 200 ítems no
  evalúa el tope.
- **`purge-purchased`**: solo los comprados quedan con `deleted_at`; responde sus
  ids; no toca los no comprados; sin comprados → `200 {deleted_ids: []}` y
  `version` no cambia (RF-19).
- **Sync — carga completa**: sin `cursor` → `items` = activos ordenados por
  RF-18, `deleted_ids: []`, `cursor = version`. `cursor` no entero / `cursor` >
  `version` → misma carga completa.
- **Sync — delta**: crear ítems y leer `version` de `show`; modificar uno y
  borrar otro; `GET items?cursor=<version_anterior>` → el modificado en `items`,
  el borrado en `deleted_ids` (solo `id`, sin `name`/`added_by`/timestamps),
  `cursor` = nueva `version`. Segunda llamada con el nuevo cursor → `items: []`,
  `deleted_ids: []`.
- **Sync — casos límite**: ítem creado y borrado entre dos cursores → llega solo
  en `deleted_ids`; cursor de una lista sobre otra → carga completa o delta
  inocuo; lista borrada + `GET items` → `404` (RF-27).
- **Aislamiento entre listas**: `PATCH /api/lists/A/items/{id de B}` → `404`
  (scoped bindings).
- **Sin auth** (RF-30, RF-31): todas las rutas responden sin token ni sesión;
  no existe `GET /api/lists` que enumere (RF-5); varias peticiones al mismo slug
  desde IPs simuladas distintas → todas `200` bajo el límite.
- **Rate limiting** (RNF): la petición 11 de `POST /api/lists` desde la misma IP
  en una hora → `429`; `sync` 61/min → `429`; `writes` 121/min → `429`.
- **XSS / texto plano** (RF-32): crear un ítem con `name` = `<script>alert(1)</script>`;
  `GET /l/{slug}` devuelve el HTML con la secuencia escapada (`&lt;script&gt;`),
  no como tag; `ItemResource` la devuelve literal sin escapar (es JSON).
- **No indexable** (RNF): `GET /l/{slug}` trae `X-Robots-Tag: noindex, nofollow`;
  `GET /robots.txt` contiene `Disallow: /l/`.
- **PWA**: `GET /manifest.json` → `200`, mime correcto, claves `name`, `icons`,
  `theme_color`, `display`; `icons` incluye `192x192` y `512x512` y los ficheros
  referenciados responden `200` con `Content-Type: image/png`; `GET /sw.js` →
  `200` con MIME de JS; `GET /offline` → `200`.
- **Comando de purga** (RF-16): sembrar lápidas con `deleted_at` viejo y reciente;
  `items:purge-tombstones --before=<fecha>` borra solo las viejas y reporta el
  conteo; sin `--before` aborta.
- **Cliente (Playwright directo, `npx playwright test`)**:
    - RF-6: abrir `/l/{slug}` guarda la entrada en `localStorage`; "quitar de mis
      listas" la borra; abrir una lista con 404 la poda; el nombre mostrado se
      refresca tras renombrar; tope de 20 entradas.
    - RF-21: el nombre de "quién agrega" se recuerda y se propone editable.
    - RF-22: el polling corre mientras la pestaña es visible y se pausa al ocultarla
      (`visibilitychange`), reanudando con una consulta inmediata.
    - RF-23: un cambio hecho por una petición directa aparece en la vista en ≤ 5 s.
    - RF-25 (cliente): al editar solo el nombre, el `PATCH` lleva solo `{name}`.
    - RF-26: sin red, una escritura falla con aviso visible y no se reintenta; la
      última vista conocida se mantiene; al volver la red, el polling se reanuda.
    - RF-27: si la lista se borra desde otra vía, el siguiente polling muestra "la
      lista ya no existe".
    - RF-32 (cliente): un ítem con `<img onerror>` en el nombre se muestra como
      texto, sin ejecutar.
- **Verificación manual** (criterios de finalización): Lighthouse "installable"
  en Chrome Android; demo en dos dispositivos con propagación ≤ 5 s.

## Qué RF cubre cada parte

| Parte                                                    | RF                                                                         |
| -------------------------------------------------------- | -------------------------------------------------------------------------- |
| T0 saneamiento (auth, `.env`, locale, Alpine/Vite, Pest) | RF-30                                                                      |
| Migraciones + modelos (`slug`, `version`, `SoftDeletes`) | RF-1, RF-10, RF-11, RF-12, RF-16, RF-17, RF-24                             |
| `ShoppingListController` + Requests                      | RF-1, RF-2, RF-3, RF-4, RF-7, RF-8, RF-9                                   |
| `ItemController` + Requests + `ItemResource`             | RF-10..RF-15, RF-17, RF-19, RF-20, RF-32                                   |
| Contador de versión + transacciones                      | RF-24, RF-25                                                               |
| Endpoint `sync`                                          | RF-18 (recolocación), RF-22, RF-24, RF-27                                  |
| `routes/api.php` sin auth + `throttle`                   | RF-5, RF-30, RF-31, RNF límite de peticiones                               |
| Middleware `NoIndex` + `robots.txt`                      | RNF no indexable                                                           |
| Comando `items:purge-tombstones`                         | RF-16                                                                      |
| `list.js` (Alpine)                                       | RF-6, RF-15, RF-18, RF-19, RF-21, RF-22, RF-23, RF-25, RF-26, RF-27, RF-32, RF-34 |
| Vistas Blade + layout (escape `{{ }}`)                   | RF-3, RF-9, RF-18, RF-19, RF-32, RF-33                                     |
| `manifest.json` + iconos + `sw.js` + `offline`           | RF-28, RF-29                                                               |
| `lang/es/validation.php`                                 | constitución 8, RNF idioma                                                 |

## Notas sobre la constitución

- **Sin conflictos tras el saneamiento de T0.** Antes de T0 el repo viola la
  constitución 4 (esqueleto de auth presente); T0 lo corrige y debe completarse
  antes de T1.
- Alpine, Pest 3.8 y Playwright son dependencias justificadas aquí
  (constitución 1): Alpine es runtime pero mínimo y sin SPA; Pest y Playwright
  son solo de desarrollo. Pest 4 se descartó por requerir PHP 8.3+.
- El `deleted_at` de RF-16 es metadato de sync, no historial navegable: sin UI ni
  endpoint que liste lápidas; `deleted_ids` expone solo el `id` (constitución 7).
- Rate limiting y `X-Robots-Tag` son cotas defensivas para hosting compartido
  (constitución 5), sin procesos persistentes.
- La purga de lápidas es un comando manual, sin scheduler ni cron
  (constitución 5); se documenta en `docs/deploy.md`.
