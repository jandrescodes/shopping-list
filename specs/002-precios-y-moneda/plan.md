# Plan técnico — Spec 002

## Estado de partida del repo

Sin saneamiento pendiente: spec 001 está cerrada y validada (`specs/001-lista-compras-familiar/validation.md`, versión `1.0.0`). El baseline está verde con **119 tests / 619 assertions** (`php artisan test`, contra MySQL real) y 22 tests de Playwright. No hay T0 que sanear; esta feature arranca en T0 = primera migración.

Dos hallazgos del código condicionan el plan y están verificados, no supuestos:

- `ItemController` pasa `$request->validated()` **directo** a `create()` y a
  `fill()`. Un campo que no esté en `Item::$fillable` valida, devuelve `201` y
  desaparece sin error. `price` tiene que entrar en `$fillable` en la misma tarea
  que su migración.
- El cast `decimal` de Laravel es **solo de lectura**: `setAttribute()` guarda el
  valor crudo (`HasAttributes.php:1116`) y `asDecimal()` (BrickMath
  `BigDecimal::of()->toScale(2, HALF_UP)`) solo se invoca al leer. Por eso
  normalizar el separador decimal es trabajo del Form Request, no del cast.
- La **cantidad** está completa en backend desde spec 001 (columna, validación,
  recurso, sync, deshacer) pero **no tiene entrada en la interfaz**: el alta solo
  envía `name` + `added_by` (`list.js:327`) y la edición en línea solo compara y
  manda el nombre (`commitEdit`, `list.js:366-383`). Es un hueco de UI, no de
  datos, y esta spec lo absorbe como RF-26 a RF-28 (`§ Clarificaciones`,
  "Fase 8").

## Estructura de módulos

- `database/migrations/*_add_price_to_items_table.php` → columna `items.price`
  (RF-1, RF-2, RF-4, RF-5)
- `database/migrations/*_add_currency_to_shopping_lists_table.php` → columna
  `shopping_lists.currency` (RF-10, RF-13)
- `app/Models/Item.php` → `$fillable`, cast `decimal:2`, mutador de precio
  (RF-1, RF-2, RF-4)
- `app/Models/ShoppingList.php` → `$fillable` de `currency` (RF-10)
- `app/Http/Requests/StoreItemRequest.php`, `UpdateItemRequest.php` → reglas y
  normalización de `price` (RF-3, RF-5, RF-6, RF-7)
- `app/Http/Requests/StoreListRequest.php` → regla de `currency` (RF-12);
  `UpdateListRequest` la hereda
- `app/Http/Resources/ItemResource.php` → `price` en la forma del ítem (RF-8)
- `app/Http/Resources/ShoppingListResource.php` → **nuevo**; forma de lista única
  para `show`, `update` y `sync` (RF-11, RF-23)
- `app/Http/Controllers/Api/ItemController.php` → `price` en el `sync` junto con
  la metadata de lista (RF-21, RF-23)
- `app/Http/Controllers/Api/ShoppingListController.php` → `currency` en `show` /
  `update`, y `ShoppingListResource` en lugar de arrays literales (RF-11, RF-22)
- `app/Support/ListVersion.php` → sin cambios; se reutiliza tal cual (RF-21, RF-22)
- `resources/js/list.js` → input de precio, edición en línea, chip de moneda,
  total, aplicación del bloque `list` del poll, `price` en el deshacer, alta y
  edición de la cantidad (RF-8, RF-11, RF-14, RF-15, RF-18, RF-24, RF-25,
  RF-26, RF-27)
- `resources/views/list.blade.php` → precio y cantidad en la segunda fila del
  alta, precio y total en el `<ul>` pre-hidratación, chip de moneda, cantidad
  editable en la fila (RF-8, RF-11, RF-14, RF-16, RF-26, RF-27, RF-28)
- `lang/es/validation.php` → mensajes de `price` y `currency` (constitución 8)

**La cantidad no toca backend**: columna, `StoreItemRequest` /
`UpdateItemRequest`, `ItemResource`, `sync` y el deshacer ya la soportan desde
spec 001 y ya tienen sus tests. RF-26 a RF-28 son solo de interfaz.

Sin rutas nuevas: las 9 del contrato siguen siendo las mismas, así que
`ApiRoutesTest` no se toca.

## Modelo de datos

### `shopping_lists`

| Columna    | Tipo        | Null | Default | Notas                                                 |
| ---------- | ----------- | ---- | ------- | ----------------------------------------------------- |
| `currency` | `string(5)` | no   | `'Bs'`  | Texto libre, sensible a mayúsculas, 1–5 chars (RF-10) |

Se añade después de `name`. Sin índice. Listas existentes heredan el default
`'Bs'` por el default de columna: **no hace falta migración de datos**.

### `items`

| Columna | Tipo             | Null | Default | Notas                                               |
| ------- | ---------------- | ---- | ------- | --------------------------------------------------- |
| `price` | `decimal(10, 2)` | sí   | —       | Monto no negativo, ≤2 decimales, ≤8 dígitos enteros |

Se añade después de `quantity`. **Sin índice**: "Fuera de alcance" prohíbe
filtrar, ordenar y agrupar por precio, así que un índice no lo consultaría
nadie y solo encarece la escritura.

`DECIMAL` en MySQL es base-10 exacto, sin coma flotante detrás: es lo que
sostiene el RNF "Precisión aritmética". Los ítems existentes quedan con
`price = NULL`, que el sistema interpreta como "sin precio" y **no** como cero
(RF-2, RF-4).

Invariantes de `price`: nullable, ≥ 0, ≤ 2 decimales, ≤ 8 dígitos enteros
(`99999999.99` máximo). `0` es un valor válido y distinguible de la ausencia
(RF-5).

`quantity` **ya existe** desde spec 001 (nullable, `string(50)`, sin índice): no
se le añade columna, default ni índice. Su hueco era de interfaz, no de datos
(RF-26, RF-27).

## Algoritmos / lógica no trivial

### 1. Normalización del precio (RF-6, RF-7)

Vive en `prepareForValidation()` de `StoreItemRequest` y `UpdateItemRequest`, en
ese orden:

1. Si la clave no viene en el request, no se toca (los Requests de `PATCH`
   validan solo lo presente).
2. Recortar espacios exteriores.
3. Si lo que queda es vacío, o son solo separadores (`,` `.` `,.`), se convierte
   en `null` — "sin precio", nunca `0`.
4. Sustituir `,` por `.` (RF-7).
5. Validar el valor canónico con
   `['nullable', 'regex:/^\d{1,8}(\.\d{1,2})?$/', 'min:0', 'max:99999999.99']`.

`$request->validated()` devuelve entonces el valor **ya normalizado**, que es lo
que se persiste. La validación va después de la normalización a propósito: si
fuera al revés, `12,50` se rechazaría antes de poder convertirse.

Consecuencias deliberately asumidas (documentadas en "Casos límite" de la spec):
`1.234,50` se rechaza (punto de miles no es un número simple), y `1.234` se
rechaza por tener 3 decimales.

### 2. Mutador del modelo (RF-2, RF-4)

`Item::setPriceAttribute(?string $value)`: recorta y convierte `''` en `null`,
copiando `setQuantityAttribute`.

Hace falta aunque el Form Request ya normalice, porque el mutador es la **última
barrera** antes de la columna: un cliente que mande `""` (un input de texto vacío
que no pasó por el Form Request de esta spec, o un `curl`) no debe acabar en
`0.00`. En MySQL no estricto, `''` sobre una `decimal` se convierte a `0` con
warning; en estricto, error 1366.

Debe ser **idempotente**: `ListVersion::write` guarda el modelo dos veces (una en
el callback, otra para sellar la versión), así que un mutador no idempotente se
aplicaría dos veces. Trim + `''`→`null` lo es.

### 3. Total pendiente (RF-15, RF-16, RF-17, RF-18, RF-19, RF-20)

Calculado **en el cliente**, en centavos enteros:

```js
const toCents = (value) => {
    if (value === null || value === undefined || value === "") {
        return 0;
    }

    return Math.round(parseFloat(value) * 100);
};
```

- `pendingTotalCents` → suma de `toCents(item.price)` sobre los ítems **no
  comprados**.
- `formattedPendingTotal` →
  `new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(pendingTotalCents / 100)`.
- Se muestra solo si `items.some((item) => !item.is_purchased && item.price !== null)`
  (RF-17, y con ello cubre RF-20: borrar el precio del último la oculta sola).

Por qué centavos enteros: `0.1 + 0.2 !== 0.3` en IEEE-754. Acumular en enteros
concentra todo el redondeo en una única división final. Por qué en el cliente y
no en un endpoint: el total es **derivado** de datos que ya están en memoria
(los ítems del último poll), así que un endpoint costaría un round-trip cada 3–4
s. Y al no ser estado, no necesita versionado ni viajar por el delta: el precio
y la moneda sí son estado, el total no.

El total del `<ul>` pre-hidratado se calcula en Blade con la misma técnica
(`(int) round($precio * 100)`, suma, y `number_format($c/100, 2, ',', '.')`)
para que la vista no parpadee antes de que `list.js` tome el control. Tras la
hidratación manda el cliente.

### 4. Metadata de lista en la sincronización (RF-22, RF-23, RF-24)

`sync()` devuelve en **ambas** ramas (cursor válido y carga completa):

```php
'list' => ['name' => $list->name, 'currency' => $list->currency],
```

`list.js::syncOnce()` la aplica tras fusionar los ítems:

```js
if (data.list) {
    if (data.list.name !== this.listName) {
        this.listName = data.list.name;
        this.rememberList();
    }

    if (data.list.currency !== this.currency) {
        this.currency = data.list.currency;
    }
}
```

La guarda `if (data.list)` no es paranoia: el service worker precachea el shell,
así que durante un despliegue puede haber un JS viejo hablando con una API vieja
o al revés. Sin la guarda, `data.list.name` lanza `TypeError` y el poll muere
para siempre. Coste: ~30 bytes por consulta de 3–4 s.

Esto cierra de paso el hueco de spec 001 (documentado en RF-22): **el
renombrado de una lista nunca llegaba a las pestañas abiertas**, porque
`ItemResource` no lleva metadata de lista y `sync` solo devolvía delta de ítems.
La moneda vive en la lista, así que sin este arreglo el cambio de moneda sería
invisible para quien no hizo el cambio.

### 5. Interacción con `ListVersion` (RF-21, RF-22)

No se toca `ListVersion`. El precio se guarda con el
`fill($request->validated())->save()` que ya usa `ItemController::update`, y la
moneda con el `fill()` de `ShoppingListController::update`; ambos ya pasan por
`ListVersion::write`.

Consecuencias que ya se siguen solas:

- Editar el precio bumpea la versión de la lista y sella la del ítem → el delta
  llega a los demás dispositivos (RF-21).
- Cambiar la moneda bumpea la versión de la lista → los demás ven un `cursor`
  nuevo en su siguiente poll y leen la moneda del bloque `list` (RF-22). No
  hace falta un delta de ítems para eso.
- El campo-por-campo de spec 001 (RF-25) sigue garantizando que editar el
  precio de un ítem no pisa el nombre ni el estado comprado de otro dispositivo.

### 6. Preservación al deshacer (RF-25)

`list.js::offerUndo()` mete `price` en `pendingUndo`, y `undoRemove()` lo
incluye en el payload solo si no es `null` (mismo patrón que `quantity` y
`added_by`). Sin esto, deshacer un borrado pierde el precio — y el `id` ya es
nuevo de todos modos (spec 001, RF-25).

### 7. Interacción con el poll y la edición en curso

`syncOnce()` ya protege la edición del nombre: si el ítem local está en estado
`editing`, conserva `draftName` y no pisa el valor que el usuario escribe
(`list.js:251-255`). Esa rama solo conoce `editing` y `draftName`, así que todo
otro borrador que viva "mientras el usuario escribe" tiene que sumarse ahí, o la
siguiente sincronización lo pisa en silencio. En esta feature hay dos:

- `draftQuantity` (T24): comparte `item.editing` con el nombre, así que basta
  con añadirlo a la condición y al spread.
- `draftPrice` (T14): se modela con `editingPrice` **propio** (el precio se
  edita desde su chip, sin abrir la edición del nombre), y la condición actual
  no lo mira: sin cubrirlo también, el precio que se está escribiendo se pierde
  con la siguiente sincronización. La mezcla queda mirando los dos flags.

### 8. Edición de la cantidad en la fila (RF-26, RF-27, RF-28)

Un solo estado de edición por ítem, el que ya existe (`item.editing`), ahora con
dos campos en lugar de uno:

- `normalize()` inicializa `draftName` **y** `draftQuantity`. El segundo se
  arranca como `item.quantity ?? ''` y no como `item.quantity`: `x-model` sobre
  `null` escribiría el string `"null"` en el input.
- `startEdit(item)` carga ambos drafts. Al entrar en edición la fila oculta el
  chip de cantidad (ya lo hace hoy con `!item.editing`) y muestra el input de
  cantidad junto al de nombre, en el mismo ancho de fila.
- `commitEdit(item)` compara los dos drafts contra el valor original del ítem y
  manda en un único `PATCH` **solo los campos que cambiaron** (escritura campo por
  campo, spec 001 RF-25); si ninguno cambió, solo cierra la edición. Este es el
  punto que rompería "editar solo la cantidad": hoy la función sale temprano
  cuando `name` no cambió, así que ese early-return hay que reemplazarlo por la
  comparación de los dos campos.
- El cierre por `blur` del input de nombre se reemplaza por un `focusout` en el
  **envoltorio que contiene los dos inputs de edición**, con chequeo
  `currentTarget.contains(relatedTarget)`. Saltar del nombre a la cantidad no
  cierra la edición (ambos están dentro del envoltorio); salir al checkbox, al
  `×`, a otro ítem o a fuera de la página sí la cierra y guarda, igual que hoy.
  Aplicado en su lugar a todo el `<li>` rompería el caso inverso: el checkbox
  quedaría dentro del área que protege, así que habría que distinguir a mano qué
  hermano es "otro input" y qué hermano no.
- El chip de cantidad es clicable (`startEdit`): es la vía de descubrimiento
  cuando la cantidad ya existe, y la única forma de agregarla a un ítem creado
  sin ella es entrar a la edición desde el nombre (RF-28: no hay chip que
  clicar).
- Si el `PATCH` falla, la edición se cierra y se muestra el error, igual que hoy
  con el nombre; los drafts se recargan en el siguiente `startEdit`, así que no
  queda estado viejo colgado.

## Decisiones técnicas

- **`decimal(10, 2)` nullable** vs `float` vs centavos enteros en BD → `DECIMAL`
  en MySQL es base-10 exacto y satisface el RNF de precisión sin conversiones.
  Descartado: `float` (deriva de coma flotante, y el RNF la prohíbe de
  entrada); centavos enteros en BD (exacto, pero ilegible en `php artisan
tinker` y en un `SELECT` para un dominio doméstico donde `DECIMAL` ya
  resuelve el problema).
- **Sin índice en `price`** → "Fuera de alcance" excluye filtrar/ordenar por
  precio; un índice sin consulta que lo use solo encarece la escritura.
- **`ShoppingListResource` nuevo** vs seguir con arrays literales → `show`,
  `update` y las **dos** ramas de `sync` necesitan `{name, currency}`; a mano
  divergen otra vez, que es exactamente el mecanismo que produjo el hueco de
  sync. `POST /api/lists` conserva su array propio porque devuelve `url`, un
  detalle de creación.
- **Normalizar coma→punto en `prepareForValidation()`** y no en el modelo ni
  solo en el cliente → el servidor es la frontera de confianza: un cliente viejo
  o un `curl` con `12,50` tiene que funcionar igual (RF-7). El cliente también
  normaliza, pero eso es cortesía, no garantía.
- **`regex` + `min` + `max`** en vez de la regla `decimal` → verificado en
  `FormatsMessages.php:260-264`: Laravel solo sustituye placeholders si existe
  un método `replace{NombreDeLaRegla}`, y **no existe `replaceDecimal`**. El
  mensaje de `lang/es/validation.php:42` usa `:decimal`, así que la regla
  `decimal` renderizaría el texto `:decimal` literal en la respuesta 422. Con
  `regex` el mensaje es propio y sale en español claro.
- **Default de moneda en el default de la columna**, sin `config/shopping.php`
  → una variable de config que deriva del default de la migración puede
  divergir en silencio. La moneda se cambia desde la vista (RF-13), que es el
  sitio donde se lee.
- **Total en el cliente** vs endpoint de resumen → derivado de datos ya en
  memoria; un endpoint añade un round-trip por poll. Al no ser estado, no
  necesita sincronización ni versionado.
- **`Intl.NumberFormat('es', …)` fijo** vs el locale del navegador → la moneda
  es texto libre (RF-10), no un código ISO, así que `style: 'currency'` no
  aplica; y con locale `en-US` saldría `Bs 1,234.50`, un separador incompatible
  con el símbolo. Coherente con el RNF "Formato de importes".
- **Fila 2 del alta = cantidad + precio** (la fila 1 sigue siendo nombre +
  botón) → a 320 px no caben tres controles en una fila, pero dos sí; apilar
  cantidad y precio en filas propias alargaría el formulario justo encima de la
  lista, en la pantalla más estrecha que hay que soportar. Deja intacto el gesto
  más repetido de la app y no rompe los 5 e2e que usan `#new-item`, porque
  `addItem` solo agrega `quantity` al payload si el usuario lo escribió.
- **Un solo estado de edición con dos campos** vs. un modo de edición por campo
  (nombre y cantidad por separado) → con un modo por campo no habría forma de
  agregar cantidad a un ítem que no la tiene (RF-28: no hay chip que clicar) y
  habría que mantener dos guardas de sincronización. El estado único reutiliza
  el guard de `syncOnce` tal cual, con `draftQuantity` sumado.
- **La cantidad no se revalida en el cliente** → se manda el texto y el servidor
  ya recorta, vacía a `null` y valida `max:50` (`prepareForValidation()` de
  `StoreItemRequest`). Duplicar esa lógica en JS es una segunda frontera que se
  puede desincronizar de la real.
- **Sin cambios de backend para la cantidad**: migración, modelo, Form Request,
  recurso y `sync` ya existen y ya tienen sus tests desde spec 001. Tocar
  alguno de esos archivos a causa de la cantidad estaría reimplementando algo
  que ya está.
- **Moneda editable en línea en el encabezado**, junto a "Renombrar" → mismo
  patrón de edición inline que el nombre y el precio, y sin catálogo de códigos
  que mantener (Clarificaciones de la spec).
- **Sin subtotal de comprados** → "Fuera de alcance" de la spec.

## Contrato de interfaz

Todas las respuestas JSON. Sin cabeceras de auth. `{slug}` resuelve por `slug`
con match exacto; `404` genérico si no existe. `cursor` es un entero opaco del
servidor. **Sin rutas nuevas** (siguen siendo 9).

| Método y ruta                                  | Cuerpo entrada                              | Éxito                                          | Errores                                 | Throttle       |
| ---------------------------------------------- | ------------------------------------------- | ---------------------------------------------- | --------------------------------------- | -------------- |
| `POST /api/lists`                              | `{name}`                                    | `201 {slug, name, url}`                        | `422`, `429`                            | `lists-create` |
| `GET /api/lists/{slug}`                        | —                                           | `200 {slug, name, currency, version, items[]}` | `404`                                   | —              |
| `PATCH /api/lists/{slug}`                      | `{name?, currency?}`                        | `200 {slug, name, currency, version}`          | `404`, `422`, `429`                     | `writes`       |
| `DELETE /api/lists/{slug}`                     | —                                           | `204`                                          | `404`, `429`                            | `writes`       |
| `GET /api/lists/{slug}/items?cursor=`          | —                                           | `200 {items[], deleted_ids[], cursor, list}`   | `404`, `429`                            | `sync`         |
| `POST /api/lists/{slug}/items`                 | `{name, quantity?, price?, added_by?}`      | `201 {item}`                                   | `404`, `422` (incl. lista llena), `429` | `writes`       |
| `PATCH /api/lists/{slug}/items/{id}`           | `{name?, quantity?, price?, is_purchased?}` | `200 {item}`                                   | `404`, `422`, `429`                     | `writes`       |
| `DELETE /api/lists/{slug}/items/{id}`          | —                                           | `204`                                          | `404`, `429`                            | `writes`       |
| `POST /api/lists/{slug}/items/purge-purchased` | —                                           | `200 {deleted_ids[]}`                          | `404`, `429`                            | `writes`       |

Formas de referencia:

- `item` (`ItemResource`): `{id, name, quantity, price, added_by, is_purchased, version}`.
  `price` es **string** (`"12.50"`) o `null`, nunca float. Nunca incluye
  `shopping_list_id` ni datos de lápida.
- `list` (`ShoppingListResource`): `{slug, name, currency, version}`.
- Bloque `list` dentro de `sync`: `{name, currency}` (sin `slug` ni `version`: el
  cliente ya sabe qué lista está mirando, y la versión llega como `cursor`).

Cambios respecto a spec 001, todos aditivos: `price` en la forma del ítem,
`currency` en la forma de lista, `currency` aceptado por `PATCH /lists/{slug}`, y
el bloque `list` en `sync`. Un cliente viejo ignora las claves nuevas; un cliente
nuevo con API vieja necesita la guarda `if (data.list)` del algoritmo 4. La
cantidad **no** aparece en esa lista de cambios: su columna, su validación y su
presencia en los cuerpos de entrada ya estaban desde spec 001, así que el
contrato no se mueve por RF-26 a RF-28.

## Riesgos

- **`price` ausente de `Item::$fillable`** → el endpoint responde `201` y el
  precio se pierde en silencio, sin ningún error visible. Mitigación: la
  migración, el `$fillable` y el test de round-trip van en la **misma** tarea, y
  el test falla si falta cualquiera de los tres.
- **`''` coerced a `0.00`** por MySQL en modo no estricto → un ítem sin precio
  sumaría cero y la fila mostraría un monto falso. Mitigación: mutador
  (algoritmo 2) + test que persiste un precio ausente y afirma `null`, no
  `0.00`.
- **`"12,50"` revienta el cast `decimal` en lectura** (`BigDecimal::of()` lanza
  `MathException` sobre un valor no numérico) → un `500` en la fila entera.
  Mitigación: normalizar antes de validar (algoritmo 1) + test que envía coma y
  punto y afirma el mismo monto persistido.
- **Suma de coma flotante en el total** → un total que no cuadra con la suma a
  ojo es exactamente el bug que mata la confianza en la feature. Mitigación:
  centavos enteros (algoritmo 3) + test con precios que en coma flotante no
  cuadran.
- **Cambio de contrato de `sync`** durante un despliegue, con el shell
  precacheado por el service worker → dos combinaciones: API vieja + JS viejo
  (bien), API nueva + JS viejo (bien, el JS ignora `list`), API vieja + JS
  nuevo (rompe sin la guarda). Mitigación: la guarda `if (data.list)`, y el
  despliegue coordinated por release (`deploy.yml` solo corre con release
  publicada o `workflow_dispatch`).
- **Doble guardado del modelo en `ListVersion::write`** → un mutador no
  idempotente se aplicaría dos veces por escritura. Mitigación: mutador
  idempotente, siguiendo el patrón ya establecido de `setQuantityAttribute`.
- **Hueco de sync heredado de spec 001** (el renombrado no llegaba a pestañas
  abiertas) → cambiar el formato de `sync` puede sorprender a la lógica de
  fusión del cliente. Mitigación: el bloque `list` va en un campo nuevo, no
  toca `items`/`deleted_ids`/`cursor`, así que la fusión existente queda intacta.
- **Pest y Playwright son suites separadas** → una puede pasar mientras la otra
  falla. Mismo riesgo residual que spec 001: mitigado por la regla de `AGENTS.md`
  § "Al terminar cualquier tarea" y la puerta de verificación de la constitución 3.
- **Deriva entre el total de Blade y el del cliente** durante la transición
  pre-hidratación → si difieren, la vista parpadea al hidratar. Mitigación: la
  misma técnica de centavos en ambos lados + el e2e del total, que solo observa
  el estado hidratado.
- **`row.getByRole('textbox')` deja de ser único** (`tests/e2e/list.spec.js:84`)
  en cuanto la edición muestra los dos inputs: Playwright falla en modo estricto
  con "resolved to 2 elements" y se pierde la única regresión que protege
  RF-25. Mitigación: acotar el locator por un atributo propio del campo
  (`data-edit-field="name" | "quantity"`) en la misma tarea que agrega el
  segundo input, dejando intacto el assert `Object.keys(...) === ['name']`.
- **`draftQuantity` arrancado en `null`** → el input mostraría el string
  `"null"` y, al confirmar, se enviaría `"null"` como cantidad. Mitigación:
  inicializar con `?? ''` en `normalize()` (algoritmo 8).
- **`commitEdit` sale temprano cuando `name` no cambió** → editar solo la
  cantidad cerraría la edición sin guardar nada, en silencio y sin error.
  Mitigación: comparar los dos drafts (algoritmo 8) + e2e que edita solo la
  cantidad y afirma el cuerpo del `PATCH`.
- **Foco saltando entre los dos inputs de la fila** cerraría la edición a mitad
  de escribir si el `blur` sigue pegado al input de nombre. Mitigación:
  `focusout` en el envoltorio de los dos inputs con
  `currentTarget.contains(relatedTarget)` (algoritmo 8).

## Estrategia de verificación

Puerta por tarea (`AGENTS.md`): `php artisan test` siempre; `npx playwright
test` cuando la tarea toque `resources/js/`; `php artisan pint` sobre lo tocado.
Ninguna tarea se marca hecha en rojo.

**Pest — tests nuevos**

- `ItemsSchemaTest` / `ShoppingListsSchemaTest`: `Schema::hasColumn` más
  `Schema::getColumnType` (`decimal`, `varchar`) y, vía
  `Schema::getColumns()`, la escala de `price` y la longitud de `currency`.
- `ItemTest` (modelo): mutador `''`→`null`, `null`→`null`, `' 12.50 '`→`'12.50'`;
  el cast de lectura devuelve string de 2 decimales (`'7'`→`'7.00'`).
- `ShoppingListTest`: una lista nueva nace con `currency = 'Bs'`.
- `StoreItemRequestTest`: pasan `12.50`, `12,5`, `12`, `0`, `' 12,50 '` y
  devuelven el valor canónico; `''` y `,` se vuelven `null`; `422` con mensaje
  en español para `-1`, `abc`, `1.999`, `1.234,50`, `999999999.99`.
- `UpdateItemRequestTest`: `price: null` valida; `price` ausente no aparece en
  `validated()`.
- `ItemControllerStoreTest`: `price` en el body → `ItemResource` devuelve
  `"12.50"` (string) y no `12.5` (float).
- `ItemControllerUpdateTest`: actualizar solo `price` no altera `name` ni
  `is_purchased`; `price: null` limpia el precio; el sello de versión sigue
  funcionando.
- `ItemControllerSyncTest`: el bloque `list` viene con `name` y `currency` en
  ambas ramas (cursor válido y carga completa); tras cambiar la moneda, la
  siguiente sincronización la trae actualizada.
- `ShoppingListControllerShowTest` (extendido): `currency` en la respuesta.
- `ShoppingListControllerUpdateTest`: cambiar moneda; `currency` de 6 caracteres
  → `422`; `"Bs "` se recorta a `Bs`.
- `ListVersionTest`: cambiar la moneda bumpea la versión de la lista. Necesita
  MySQL real por el `lockForUpdate` — es una de las razones por las que la suite
  no corre en SQLite (`AGENTS.md`).

**Pest — tests existentes que se actualizan, no se relajan**

- `ShoppingListControllerShowTest:24-25`: el assert de claves exactas del ítem
  pasa a `['id','name','quantity','price','added_by','is_purchased','version']`.
  Sigue siendo un assert **exacto**: si mañana aparece una clave sin querer, falla.
- `ItemControllerSyncTest:45`: el `assertExactJson` incorpora el bloque `list`.
- `ApiRoutesTest`: sin cambios; siguen siendo 9 rutas.
- `StoreItemRequestTest` / `UpdateItemRequestTest`: los mensajes de
  validación se comprueban contra `lang/es/validation.php`, así que se les añade
  el caso de `price`.
- `ListPageTest:15`: además de `id="new-item"`, afirma que el alta tiene el
  input de cantidad (RF-26). No hay más Pest nuevo para la cantidad:
  `ItemControllerStoreTest`, `ItemControllerUpdateTest`,
  `StoreItemRequestTest` y `ItemControllerSyncTest` ya cubren guardar, validar,
  normalizar, vaciar a `null` y propagar la cantidad desde spec 001.

**Playwright — `tests/e2e/list.spec.js` (extendido)**

- Agregar un ítem con cantidad → el chip se ve en la fila; agregarlo sin
  cantidad → no hay chip (RF-26, RF-28).
- Editar solo la cantidad → un único `PATCH` cuyo body es exactamente
  `['quantity']`, con el nombre y el estado comprado intactos (RF-27).
- Editar solo el nombre dejando la cantidad igual → `PATCH` con `['name']`,
  que es la regresión de RF-25 que hoy vive en `list.spec.js:84` con un locator
  que hay que acotar.
- Mover el foco del input de nombre al de cantidad no dispara ningún `PATCH`
  (regresión del cierre por `focusout`).

**Playwright — `tests/e2e/list-price.spec.js` (nuevo)**

- Agregar un ítem con precio → chip en la fila y total correctos.
- Editar el precio en línea → el total se recalcula sin recargar.
- Borrar el precio → el chip desaparece y el total se oculta si era el último.
- Cambiar la moneda → el chip del encabezado y el símbolo del total cambian.
- **Regresión RF-25**: deshacer una eliminación conserva el precio.
- **Regresión RF-22/RF-24**: un cambio de moneda (y de nombre) hecho en un
  dispositivo aparece en un segundo dispositivo con la lista abierta vía
  polling — reutilizando el patrón de dos páginas de `list-polling.spec.js`.
- `12,50` y `12.50` producen el mismo total.
- Precio `0` en un ítem no comprado hace que el total se muestre.

**Verificación manual** (no automatizable en dos celulares): anotar un precio
revisando el total antes de pagar, y cambiar la moneda viendo que el otro
celular se actualiza solo.

## Qué RF cubre cada parte

| Parte                                                               | RF                                                    |
| ------------------------------------------------------------------- | ----------------------------------------------------- |
| Migración `items.price` + modelo (`$fillable`, cast, mutador)       | RF-1, RF-2, RF-4, RF-5                                |
| Migración `shopping_lists.currency` + modelo                        | RF-10, RF-13                                          |
| `StoreItemRequest` / `UpdateItemRequest`                            | RF-1, RF-3, RF-4, RF-5, RF-6, RF-7                    |
| `StoreListRequest` (heredado por `UpdateListRequest`)               | RF-11, RF-12                                          |
| `ItemResource`                                                      | RF-8                                                  |
| `ShoppingListResource` + `ShoppingListController`                   | RF-11, RF-13, RF-22                                   |
| `ItemController::sync` (bloque `list`)                              | RF-21, RF-22, RF-23                                   |
| `ListVersion` (sin cambios, reutilizado)                            | RF-21, RF-22                                          |
| `list.js` — total en centavos + formato                             | RF-14, RF-15, RF-16, RF-17, RF-18, RF-19, RF-20       |
| `list.js` — input de precio, edición en línea, chip de moneda       | RF-1, RF-3, RF-4, RF-8, RF-11                         |
| `list.js` — aplicación del bloque `list` del poll                   | RF-22, RF-24                                          |
| `list.js` — `price` en `pendingUndo`                                | RF-25                                                 |
| `list.js` — `draftQuantity`, `commitEdit` multi-campo, `focusout`   | RF-26, RF-27                                          |
| `list.blade.php` (pre-hidratación, alta, encabezado)                | RF-8, RF-11, RF-13, RF-14, RF-16, RF-26, RF-27, RF-28 |
| Backend de `quantity` (sin cambios: model, request, resource, sync) | RF-26, RF-27 (ya cubierto por spec 001)               |
| `lang/es/validation.php`                                            | constitución 8, RNF idioma                            |
| `ApiRoutesTest` (sin cambios: 9 rutas)                              | RNF límite de peticiones                              |

## Notas sobre la constitución

- **Sin conflictos.** Se cumple la 1 (cero dependencias nuevas: `Intl` es del
  navegador, no un paquete), la 3 (verificación como puerta, en las dos suites),
  la 4 (sin auth: la moneda y el precio se editan con el `slug`, igual que el
  nombre), la 5 (sin colas, schedulers ni procesos nuevos), la 6 (MySQL vía
  Eloquent, `snake_case` en BD) y la 9 (Form Requests para `price` y `currency`).
- **La 7 es la línea más cercana y no choca.** Dice que la app "no lleva
  historial/auditoría (más allá de un campo de texto libre opcional)". `price` y
  `currency` son el **estado actual** de un ítem y de una lista, no un registro
  de lo que se gastó: el spec deja "historial de lo gastado" y "control de
  gastos" explícitamente fuera de alcance. Es el mismo tratamiento que ya reciben
  `quantity` y `added_by` desde spec 001 (ver la aclarificación RF-12 de esa
  spec).
- **La 8 (idioma)** obliga a que el mensaje de `price` y de `currency` estén en
  español, y a que el código siga en inglés: `$fillable`, cast, nombres de test y
  de método, en inglés; solo el texto visible y `lang/es/`, en español.
- **La 10 (mobile-first)** es la que justifica la decisión del formulario de
  alta en dos filas y el símbolo fuera de cada fila.
