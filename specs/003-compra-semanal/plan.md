# Plan técnico — Spec 003

## Estado de partida del repo

Sin saneamiento pendiente: specs 001 y 002 están cerradas; baseline `158 Pest + 39 Playwright` (roadmap, 1.2.3). No hay T0 que sanear; esta feature arranca en T0 = primera prueba.

Hallazgos del código que condicionan el plan (verificados, no supuestos):

- `ListVersion::write` bloquea la fila de la lista, sube la versión **una vez** y sella `version` en cada `Item` que el callback devuelve. `purgePurchased` ya sigue el patrón "pre-chequeo sin bump + escritura bloqueada" (`ItemController`, `ShoppingList::purgePurchasedItems`); reiniciar lo replica.
- `items.version` y `shopping_lists.version` son `unsignedBigInteger default 0`. Una lista duplicada nace en versión 0 con ítems en versión 0: un dispositivo que la abre hace carga completa (cursor ausente), así que no necesita delta.
- El 404 uniforme (`bootstrap/app.php`) y el `ThrottleRequests` corren en ese orden: el límite se evalúa **antes** de resolver `{list}` (caso límite de spec 003 sobre duplicar un `slug` inexistente).
- `list.js` ya tiene una sola ranura de deshacer de borrado (`pendingUndo` / `undoTimer`, 5 s) y un aviso `bg-blue-50`; las clases `bg-green-50`, `bg-gray-100`, `bg-amber-50` y `bg-blue-50` están reservadas para e2e (`AGENTS.md`).
- El mensaje de 429 de creación de listas vive duplicado dentro de `home.js` (minutos); `list.js` solo tiene el de escrituras (segundos).
- `public/sw.js` precachea un `STATIC_SHELL` y `/build/*`; `/l/{slug}` no se cachea.

## Estructura de módulos

- `routes/api.php` → 3 rutas nuevas (RF-1, RF-4, RF-9, RF-17):
  `POST /lists/{list}/items/reset-purchased`, `POST /lists/{list}/items/restore-purchased` (ambas `throttle:writes`, dentro del grupo `scopeBindings` junto a `purge-purchased`) y `POST /lists/{list}/duplicate` (`throttle:lists-create`).
- `app/Http/Controllers/Api/ItemController.php` → `resetPurchased` y `restorePurchased` (RF-1, RF-4, RF-4a, RF-7, RF-7a).
- `app/Http/Controllers/Api/ShoppingListController.php` → `duplicate` (RF-9 a RF-14, RF-16, RF-19, RF-20).
- `app/Http/Requests/RestorePurchasedRequest.php` → **nuevo**; valida `ids` (constitución 9).
- `app/Models/ShoppingList.php` → lógica de dominio: `resetPurchasedItems()`, `restorePurchasedItems(array $ids)`, `duplicate()` y el recorte del nombre de la copia (constitución 9: reglas de negocio en el modelo).
- `resources/js/throttle.js` → **nuevo**; helper compartido `throttledListsMessage(retryAfter)` que usan `home.js` y `list.js` (RF-18). No es entrada de Vite: se importa.
- `resources/js/list.js` → `resetPurchased`, `undoReset`, `duplicateList`, `exportText` / `copyAsText`, ranura de deshacer compartida (RF-2 a RF-8, RF-15, RF-18, RF-21 a RF-26).
- `resources/js/home.js` → pasa a usar `throttle.js` (sin cambio de comportamiento).
- `resources/views/list.blade.php` → tres botones nuevos en `#list-menu`, aviso de reinicio con su propio `id`, aviso de copiado y campo de respaldo con su propio `id` (RNF de avisos).
- `public/sw.js` → `CACHE_VERSION` pasa a `v4`: `STATIC_SHELL` incluye `/` (la home, cache-first) y `home.js` cambia al usar `throttle.js`; `/l/{slug}` no se cachea.
- Docs: `docs/roadmap.md`, `docs/app-flow.md`, `AGENTS.md` (rutas/convención del deshacer compartido), `CHANGELOG.md` (`[1.3.0]`).

Sin migraciones: no hay columnas nuevas, `docs/schema.md` no cambia.

## Modelo de datos

Sin cambios de esquema. Una lista duplicada es un `ShoppingList` normal (slug nuevo por el hook `creating`, CSPRNG de 16 bytes, no derivado de la original) con `Item` copiados; nada guarda relación entre original y copia (constitución 7: sin historial ni auditoría).

## Algoritmos no triviales

### Reiniciar (RF-1, RF-7, RF-7a, RF-8)

1. Si `hasPurchasedItems()` es falso → `200 {"reset_ids": []}` sin tocar la versión (igual que `purgePurchased`).
2. `ListVersion::write`: dentro del bloqueo, selecciona los ítems **activos** con `is_purchased = true` según la BD en ese instante, les pone `is_purchased = false` y los devuelve; el helper sella la versión nueva en cada uno.
3. Responde `{"reset_ids": [...]}`.

Bordes: dos reinicios seguidos → el segundo cae en (1); un ítem marcado por otro dispositivo justo antes del bloqueo entra en el reinicio (gana la BD, no la creencia del cliente); soft-deleted nunca entra (la relación excluye lápidas).

### Deshacer reinicio (RF-4, RF-4a, RF-7)

1. `ids` validado: array de enteros positivos, sin duplicados, máx. 1000.
2. Pre-chequeo sin bump: si ningún id corresponde a un ítem activo de **esa lista** y desmarcado → `200 {"restored_ids": []}`.
3. `ListVersion::write`: dentro del bloqueo, filtra `whereIn(id, ids)` ∧ de la lista ∧ activo ∧ `is_purchased = false`, los marca comprados y los devuelve.
4. Responde `{"restored_ids": [...]}`.

Bordes: ids de otra lista o inexistentes se ignoran en silencio (no revela existencia); un ítem borrado o vuelto a marcar por otro dispositivo entre tanto queda intacto (RF-4); ids repetidos no duplican el trabajo. El pre-chequeo es previo al bloqueo, como en `purgePurchased`: ante una carrera puede subir la versión sin cambios, lo que solo cuesta un delta vacío.

### Duplicar (RF-9 a RF-14, RF-16, RF-20)

Una sola `DB::transaction`:

1. Crea la lista nueva: `name` = `mb_substr('Copia de '.$original->name, 0, 60)` con `rtrim` posterior; `currency` de la original.
2. Lee los ítems activos de la original ordenados por `created_at, id` (orden de creación) y los inserta en ese orden con `name`, `quantity`, `price`, `is_purchased = false`, `added_by = null`. El orden de inserción asigna `id` ascendente, que es el desempate de `orderedActive`.
3. Responde `201 {slug, name, url}` con `Location`, igual forma que `store`.

Bordes: lista vacía → lista nueva vacía (RF "caso límite"); más de 200 ítems activos se copian todos (el tope solo aplica en el alta); si algo falla se revierte toda la transacción (RF-20); doble envío → dos listas, cada una consume cupo; nombre de 60 caracteres → `mb_substr` evita el error de validación y el recorte en bytes.

### Exportar texto (RF-21 a RF-26)

Función pura en `list.js` sobre los ítems en memoria; sin servidor:

- Primera línea: `listName`.
- Ítems en el mismo orden que la vista (pendientes, luego comprados; creación dentro de cada grupo).
- Línea: `- [ ] <nombre>` + ` (<cantidad>)` si hay cantidad + ` — <precio>` si hay precio; `- [x]` para comprados. Saltos de línea del nombre/cantidad → un espacio.
- Precio con el mismo `AMOUNT_FORMAT` de la interfaz y sin símbolo.
- Última línea, si `hasPendingPrice`: `Total pendiente: <moneda> <formattedPendingTotal>`.
- Entrega: `navigator.clipboard.writeText`; si no existe o rechaza → muestra el texto en un `<textarea readonly>` seleccionado (RF-26).

## Decisiones técnicas

| #   | Decisión                                                                                                                                                        | Alternativa descartada y por qué                                                                                                                            |
| --- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| D1  | Reiniciar y restaurar son dos rutas `POST` de una petición cada una.                                                                                            | Un `PATCH` por ítem: una lista de 200 gastaría 200 escrituras del límite de 120/min (RNF de cotas).                                                         |
| D2  | El deshacer envía los `reset_ids` y el servidor filtra por su BD.                                                                                               | Guardar un snapshot en servidor: añade estado persistente y limpieza (constitución 5/7). Que el cliente decida qué restaurar: vulnerable a carreras (RF-4). |
| D3  | `duplicate` va en `ShoppingListController` con `throttle:lists-create`.                                                                                         | Reutilizar `store` con un parámetro `from`: mezcla dos operaciones y haría que el Form Request de creación admita un `slug` ajeno.                          |
| D4  | La lógica vive en `ShoppingList` (métodos de dominio) y el controlador solo orquesta.                                                                           | Servicio en `App\Support`: sobra para tres consultas sobre la propia lista; `purgePurchasedItems` ya vive en el modelo.                                     |
| D5  | Exportar es 100 % cliente.                                                                                                                                      | Endpoint de texto: gastaría peticiones, repetiría el formato de importes en PHP y duplicaría la fuente de verdad del total (RF-24).                         |
| D6  | Una sola ranura de deshacer compartida: el último aviso reemplaza al anterior.                                                                                  | Dos ranuras simultáneas: dos avisos apilados en 320 px y más estado para razonar.                                                                           |
| D7  | Aviso de reinicio con `id="reset-undo"` y fondo propio (`bg-sky-50`); aviso de copiado `id="export-notice"` con `bg-teal-50`.                                   | Reutilizar `bg-blue-50`/`bg-green-50`: rompe los locators de e2e (`AGENTS.md`).                                                                             |
| D8  | Helper `throttle.js` compartido para el 429 de listas.                                                                                                          | Copiar el texto en `list.js`: dos copias que divergen (ya hay una duplicada en `home.js`).                                                                  |
| D9  | Menú de opciones: Renombrar, Moneda, **Copiar como texto**, **Duplicar lista**, **Reiniciar compras**, Eliminar lista. "Reiniciar" con `x-show="hasPurchased"`. | Botones sueltos bajo el encabezado: ya hay "Compartir enlace"; el menú es el patrón establecido en 1.2.1.                                                   |
| D10 | `ids` con máximo 1000.                                                                                                                                          | Sin tope: acepta cuerpos arbitrariamente grandes. 200 sería un tope falso porque una lista puede pasar de 200 activos (spec 003, Clarificaciones).          |
| D11 | Sin dependencias nuevas.                                                                                                                                        | Librería de portapapeles o de formato: constitución 1 exige justificarlas y `navigator.clipboard` + `Intl` bastan.                                          |

## Contrato de interfaz

Todas sin autenticación (constitución 4), JSON siempre, 404 uniforme para lista inexistente.

| Método y ruta                                    | Entrada                                             | Éxito                                                             | Errores                                                                        |
| ------------------------------------------------ | --------------------------------------------------- | ----------------------------------------------------------------- | ------------------------------------------------------------------------------ |
| `POST /api/lists/{list}/items/reset-purchased`   | —                                                   | `200 {"reset_ids": [int]}` (vacío si no había comprados)          | `404` uniforme; `429` (`writes`, con `Retry-After`)                            |
| `POST /api/lists/{list}/items/restore-purchased` | `ids`: array de enteros ≥ 1, 1..1000, sin repetidos | `200 {"restored_ids": [int]}` (vacío si nada aplicaba)            | `422` validación; `404`; `429`                                                 |
| `POST /api/lists/{list}/duplicate`               | —                                                   | `201 {"slug","name","url"}` + `Location`, igual que `POST /lists` | `404` (consume cupo `lists-create`); `429` (`lists-create`, con `Retry-After`) |

Cliente (`list.js`): `resetPurchased()` → actualiza `is_purchased` de `reset_ids` y abre el aviso `#reset-undo` 5 s; `undoReset()` → `restore-purchased` con esos ids y marca como comprados los `restored_ids` de la respuesta (el resto llega por sincronización); `duplicateList()` → `POST` y `window.location.assign('/l/' + slug)`; `copyAsText()` → texto de RF-22 a RF-24.

## Riesgos

| Riesgo                                                                   | Mitigación / por qué se acepta                                                                                        |
| ------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------- |
| Duplicar gasta cupo `lists-create` (10/h) incluso con `slug` inexistente | Aceptado y documentado en la spec; el límite es defensivo, no de uso familiar.                                        |
| Carrera: el pre-chequeo sin bloqueo puede subir la versión sin cambios   | Aceptado: solo produce un delta vacío; es el mismo comportamiento de `purgePurchased`.                                |
| Deshacer reinicio tras recargar la página se pierde                      | Aceptado (spec): es estado del cliente, como el deshacer de borrado.                                                  |
| `navigator.clipboard` bloqueado (HTTP no seguro, permisos)               | Campo de respaldo seleccionable (RF-26); en producción el sitio es HTTPS.                                             |
| Un test e2e del aviso colisiona con clases reservadas                    | D7 + localizar por `id`; verificar que `#list-app .bg-*` de los tests existentes siguen resolviendo un solo elemento. |
| `ApiRoutesTest` afirma "exactamente las nueve rutas"                     | Se actualiza a doce en la misma tarea que añade las rutas.                                                            |
| Texto exportado desfasado del total de la interfaz                       | El texto reutiliza `AMOUNT_FORMAT`, `formatPrice` y `formattedPendingTotal` (RF-24); un test compara ambos.           |

## Estrategia de verificación

Suite automatizada (puerta de cada tarea: `php artisan test`, y `npx playwright test` si toca `resources/js/`):

- **Pest** (MySQL real):
    - `ItemControllerResetPurchasedTest`: marca solo comprados activos, devuelve `reset_ids`, no cambia nombre/cantidad/precio, sin comprados no sube la versión, doble reinicio, lista inexistente 404, delta de sync lo trae, 429 con `Retry-After`.
    - `ItemControllerRestorePurchasedTest`: restaura solo existentes y desmarcados, ignora ids ajenos/borrados/ya comprados, valida `ids`, no revela existencia de ítems de otra lista, una sola subida de versión.
    - `ShoppingListControllerDuplicateTest`: slug nuevo, nombre "Copia de" con recorte a 60 caracteres multibyte, moneda heredada, ítems sin marcar con nombre/cantidad/precio y sin `added_by`, orden de creación, no copia borrados, original intacta, lista vacía, más de 200 ítems, atomicidad ante fallo, `201` + `Location`, 404 uniforme.
    - `RateLimitingTest`: `duplicate` consume `lists-create`; las dos nuevas de ítems consumen `writes`.
    - `ApiRoutesTest`: doce rutas, sin auth.
- **Playwright** (`tests/e2e/list-weekly.spec.js`): reiniciar desmarca y muestra `#reset-undo`; deshacer restaura solo lo que sigue desmarcado (con un cambio ajeno intermedio vía API); reemplazo entre avisos de deshacer; duplicar navega a la lista nueva con los precios; 429 de duplicar muestra el texto en minutos; copiar escribe el texto exacto (permiso de portapapeles concedido) y el respaldo aparece sin permiso; el texto coincide con el total mostrado.
- **UI**: axe-core a 375 y 1280 px sin violaciones (incluidos los dos avisos nuevos) y una pasada de teclado por el menú; targets de 44 px.
- **Cierre**: `vendor/bin/pint`, `docs/roadmap.md` y `docs/app-flow.md` actualizados, `/sdd:validate`.

## Cobertura de RF

| RF                                                    | Parte del plan que lo cubre                                                                         |
| ----------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| RF-1, RF-7, RF-7a                                     | `resetPurchased` + `ShoppingList::resetPurchasedItems` + `ListVersion::write`                       |
| RF-2, RF-3, RF-5                                      | `resetPurchased()` del cliente, aviso `#reset-undo` (5 s)                                           |
| RF-4, RF-4a                                           | `restorePurchased` + `RestorePurchasedRequest` + `undoReset()`                                      |
| RF-6                                                  | `x-show="hasPurchased"` en el menú (D9)                                                             |
| RF-8                                                  | `pendingTotalCents` existente, que recalcula al cambiar `items`                                     |
| RF-9, RF-10, RF-11, RF-12, RF-13, RF-14, RF-16, RF-20 | `ShoppingList::duplicate()` en transacción, `ShoppingListController::duplicate`                     |
| RF-15                                                 | `duplicateList()` navega a `/l/{slug}`; el registro en "mis listas" ya lo hace la carga de la lista |
| RF-17, RF-18                                          | `throttle:lists-create` + `throttle.js`                                                             |
| RF-19                                                 | 404 uniforme de `bootstrap/app.php`                                                                 |
| RF-21 a RF-26, RF-22a                                 | `exportText()` / `copyAsText()` en `list.js`, aviso `#export-notice`, respaldo `#export-fallback`   |
| RNF avisos                                            | D7                                                                                                  |
| RNF cotas                                             | D1, D2                                                                                              |
