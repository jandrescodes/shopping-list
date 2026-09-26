# Tareas — Spec 002

Tareas de <30 min, ordenadas por dependencia. Cada una: sus RF, un checkbox y
una línea "Hecho cuando:" verificable. Derivadas de `plan.md`; los RF salen de
`spec.md`.

Puerta de verificación por tarea (`AGENTS.md`): `php artisan test` siempre;
`npx playwright test` cuando la tarea toque `resources/js/`; `php artisan pint`
sobre lo tocado. Ninguna tarea se marca hecha en rojo.

Dos reglas que atraviesan todo el bloque y conviene no olvidar al ejecutar:

- **El campo `price` entra en `Item::$fillable` en la misma tarea que su
  migración** (T0 + T1). `ItemController` pasa `$request->validated()` directo a
  `create()`/`fill()`: sin el `$fillable` la API responde `201` y el precio se
  pierde en silencio, sin ningún error visible.
- **Los tests existentes que se rompen se arreglan en la misma tarea que el
  código que los rompe** (T8 y T11), porque la puerta exige verde. Se actualizan
  conservando la exactitud de la aserción, nunca relajándola.

## Datos y modelos

- [x] T0. Migración `add_price_to_items_table`: `price` `decimal(10,2)` nullable
      después de `quantity`. **Sin índice**: "Fuera de alcance" excluye
      filtrar/ordenar por precio, así que un índice no lo consultaría nadie.
      (RF-1, RF-2, RF-4, RF-5)
      Hecho cuando: `php artisan migrate` añade la columna y
      `ItemsSchemaTest` verifica `Schema::hasColumn('items','price')`,
      `getColumnType() === 'decimal'` y, vía `Schema::getColumns('items')`, la
      escala 2.
- [x] T1. `app/Models/Item.php`: `price` en `$fillable` (obligatorio, ver nota
      de cabecera), `'price' => 'decimal:2'` en `$casts`, y
      `setPriceAttribute(?string)` que recorta y convierte `''` en `null`,
      copiando el patrón de `setQuantityAttribute`. El mutador debe ser
      **idempotente**: `ListVersion::write` guarda el modelo dos veces. (RF-2, RF-4)
      Hecho cuando: `ItemTest` cubre `''`→`null`, `null`→`null`,
      `' 12,50 '`→`'12,50'`, y que la lectura de un `7` persistido devuelve el
      string `'7.00'`.
- [x] T2. Migración `add_currency_to_shopping_lists_table`: `currency`
      `string(5)` default `'Bs'` después de `name`. Sin índice. Sin migración de
      datos: las listas existentes heredan el default de columna. (RF-10, RF-13)
      Hecho cuando: `php artisan migrate` añade la columna y
      `ShoppingListsSchemaTest` verifica `hasColumn`, `getColumnType() ===
'varchar'` y longitud 5.
- [x] T3. `app/Models/ShoppingList.php`: `currency` en `$fillable`.
      (RF-10)
      Hecho cuando: `ShoppingListTest` demuestra que la factory crea una lista
      con `currency === 'Bs'` sin pasarlo explícitamente.

## Validación (Form Requests)

- [x] T4. `StoreItemRequest`: normalización de `price` en
      `prepareForValidation()` —trim, `''` o solo separadores (`,` `.` `,.`) a
      `null`, y `,`→`.`— y reglas
      `['nullable','regex:/^\d{1,8}(\.\d{1,2})?$/','min:0','max:99999999.99']`,
      con la etiqueta `price` en español en `attributes()`. La normalización va
      **antes** de validar; si fuera al revés, `12,50` se rechazaría antes de
      poder convertirse. (RF-1, RF-5, RF-6, RF-7)
      Hecho cuando: `StoreItemRequestTest` acepta `12.50`, `12,5`, `12`, `0` y
      `' 12,50 '` devolviendo el valor canónico; `''` y `,` se vuelven `null`; y
      `-1`, `abc`, `1.999`, `1.234,50` y `999999999.99` devuelven 422 con
      mensaje en español.
- [x] T5. `UpdateItemRequest`: mismas reglas de `price` con `sometimes` delante,
      reutilizando la normalización de T4 en lugar de duplicarla (método
      compartido o trait, no copia). `sometimes` es lo que permite limpiar el
      precio con `price: null` sin exigir el campo. (RF-3, RF-4)
      Hecho cuando: `UpdateItemRequestTest` cubre que `price: null` valida, que
      `price` ausente **no** aparece en `validated()`, y que enviar solo
      `price` no dispara las reglas de `name`.
- [x] T6. `StoreListRequest` (y `UpdateListRequest`, que hereda): regla
      `currency` con `sometimes`, `string`, `max:5` y trim; vacío tras trim se
      rechaza (RF-12). (RF-11, RF-12)
      Hecho cuando: `ShoppingListControllerUpdateTest` cambia la moneda a
      `"Bs "` y se guarda como `Bs`; `"Bs123456"` devuelve 422; `''` devuelve 422.
- [x] T7. `lang/es/validation.php`: mensajes en español de `price` y `currency`.
      **No usar la regla `decimal`**: verificado en
      `FormatsMessages.php:260-264` que Laravel solo sustituye placeholders si
      existe `replace{NombreRegla}` y no hay `replaceDecimal`, así que el
      `:decimal` de la línea 42 se renderizaría literal. (constitución 8, RNF
      idioma)
      Hecho cuando: un 422 de `price` devuelve texto en español sin el literal
      `:decimal` ni `:attribute` en el cuerpo.

## API — Recursos y controladores

- [x] T8. `app/Http/Resources/ItemResource.php`: añadir `price` entre
      `quantity` y `added_by`. Viaja como **string** (`"12.50"`) o `null`,
      nunca float. En la misma tarea, actualizar el assert de claves exactas de
      `ShoppingListControllerShowTest:24-25` a
      `['id','name','quantity','price','added_by','is_purchased','version']` —
      sigue siendo exacto. (RF-8)
      Hecho cuando: `ItemControllerStoreTest` manda `price: '12,50'` y la
      respuesta trae `"12.50"` (string, con `assertIsString` sobre el valor, no
      solo comparación laxa); `php artisan test` en verde.
- [x] T9. `app/Http/Resources/ShoppingListResource.php` (nuevo):
      `{slug, name, currency, version}`. (RF-11, RF-23)
      Hecho cuando: `ItemResourceTest`-style test propio verifica las 4 claves
      exactas y que `currency` sale con el valor persistido.
- [x] T10. `ShoppingListController`: `show` y `update` con
      `ShoppingListResource` en lugar de arrays literales; `store` conserva su
      array propio porque devuelve `url` (detalle de creación). `update` acepta
      `currency` por el Form Request de T6 y sigue pasando por
      `ListVersion::write`. (RF-11, RF-13, RF-22)
      Hecho cuando: `ShoppingListControllerShowTest` comprueba `currency` en la
      respuesta; `ShoppingListControllerUpdateTest` comprueba que cambiar solo
      la moneda no altera el `name` ni el `slug` y que devuelve la versión nueva.
- [x] T11. `ItemController::sync`: bloque `list` con `{name, currency}` en
      **ambas** ramas (cursor válido y carga completa). En la misma tarea,
      actualizar el `assertExactJson` de `ItemControllerSyncTest:45` para que
      incluya el bloque, y añadir el caso de que tras cambiar la moneda la
      siguiente sincronización la trae actualizada. Esto cierra además el hueco
      heredado de spec 001: el renombrado nunca llegaba a pestañas abiertas.
      (RF-22, RF-23)
      Hecho cuando: `php artisan test` en verde y `ListVersionTest` demuestra
      que cambiar la moneda bumpea la versión de la lista (necesita MySQL real
      por el `lockForUpdate`).

## Frontend — Vistas Blade

- [x] T12. `list.blade.php`: input de precio opcional en el formulario de alta,
      **en una segunda fila** debajo de nombre+botón (a 320 px no caben tres
      controles apretados, y así el gesto más repetido de la app y los 5 e2e que
      usan `#new-item` no se tocan), con `inputmode="decimal"`; y en el `<ul>`
      pre-hidratado, el precio en la fila y el total al pie calculados con la
      misma técnica de centavos enteros
      (`(int) round($precio * 100)`, suma, `number_format($c/100, 2, ',', '.')`).
      (RF-1, RF-8, RF-14, RF-16)
      Hecho cuando: el HTML renderizado en servidor muestra el precio junto al
      nombre de un ítem que lo tiene y no muestra marcador en uno que no; el
      total aparece con el símbolo de la moneda de la lista. Esa segunda fila
      después pasa a compartirse con la cantidad (T22).
- [x] T13. `list.blade.php`: chip de moneda editable en el encabezado, junto a
      "Renombrar", con el mismo patrón de edición inline que el nombre.
      (RF-11, RF-14)
      Hecho cuando: el encabezado muestra la moneda vigente y al activarse la
      edición aparece un input con ese valor.

## Frontend — Alpine (`resources/js/list.js`)

- [x] T14. Estado de precio: `normalize()` añade `editingPrice` y `draftPrice`;
      `addItem()` incluye `price` en el payload (normalizando coma→punto en el
      cliente, por cortesía — la garantía es el servidor); `startEditPrice()` y
      `commitEditPrice()` con `PATCH` parcial de un solo campo, y
      `clearPrice()` enviando `price: null`; y `syncOnce()` conserva
      `editingPrice`/`draftPrice` en la mezcla de un ítem en edición, porque la
      condición actual solo mira `editing` (`list.js:253`) y sin esto el precio
      que se está escribiendo se pierde con la siguiente sincronización.
      (RF-1, RF-3, RF-4, RF-8)
      Hecho cuando: `npm run build` compila y `npx playwright test` en verde
      (la puerta de esta tarea es Playwright, no solo Pest).
- [x] T15. Moneda: `currency` en el estado del componente, leída de `load()` y
      del bloque `list` del poll; `renameCurrency(value)` con `PATCH` a
      `/api/lists/{slug}`; aplicar trim antes de enviar. (RF-11)
      Hecho cuando: Playwright cambia la moneda y el chip del encabezado y el
      símbolo del total se actualizan sin recargar.
- [x] T16. Total pendiente: helper `toCents(value)` (devuelve 0 para
      `null`/`undefined`/`''`; si no, `Math.round(parseFloat(value) * 100)`),
      getter `pendingTotalCents` sumando solo ítems **no comprados**, y getter
      `formattedPendingTotal` con
      `Intl.NumberFormat('es', {minimumFractionDigits:2, maximumFractionDigits:2})`.
      Visible solo si hay algún ítem no comprado con precio. (RF-15, RF-16,
      RF-17, RF-18, RF-19, RF-20)
      Hecho cuando: Playwright cubre que el total se recalcula al editar un
      precio y al marcar/desmarcar un ítem, y que se oculta al borrar el precio
      del último ítem no comprado.
- [x] T17. Poll: en `syncOnce()`, aplicar el bloque `list` tras fusionar los
      ítems, actualizando `listName` (y `rememberList()`) y `currency`, con guarda
      `if (data.list)` — sin ella, un JS cacheado hablando con una API vieja
      lanza `TypeError` y el poll muere para siempre. (RF-22, RF-24)
      Hecho cuando: Playwright de dos páginas ve el cambio de moneda (y de
      nombre) del dispositivo A en el B sin recargar.
- [x] T18. Deshacer: `offerUndo()` mete `price` en `pendingUndo` y
      `undoRemove()` lo incluye en el payload solo si no es `null`, igual que
      `quantity` y `added_by`. (RF-25)
      Hecho cuando: Playwright borra un ítem con precio, deshace y el precio
      vuelve a estar en la fila y en el total.

## Tests de navegador

- [x] T19. `tests/e2e/list-price.spec.js` (nuevo): alta con precio visible en
      la fila y en el total; edición en línea recalculando el total; borrado del
      precio ocultando el chip y, si era el último, el total; `12,50` y `12.50`
      produciendo el mismo total; precio `0` en un ítem no comprado haciendo que
      el total se muestre. (RF-1, RF-3, RF-4, RF-7, RF-8, RF-15, RF-16, RF-17,
      RF-20)
      Hecho cuando: `npx playwright test` en verde para el archivo nuevo, y
      los 5 archivos e2e preexistentes siguen en verde sin tocar sus
      selectores.
- [x] T20. `tests/e2e/list-price.spec.js`: regresión del deshacer — borrar un
      ítem con precio y deshacer conserva el precio. Es la regresión más fácil de
      no ver: sin T18 el precio se pierde y nada falla visiblemente. (RF-25)
      Hecho cuando: el test falla si se quita `price` de `pendingUndo`.
- [x] T21. `tests/e2e/list-price.spec.js`: regresión de propagación — cambio de
      moneda y de nombre en el dispositivo A visible en el dispositivo B con la
      lista abierta, vía polling, reutilizando el patrón de dos páginas de
      `tests/e2e/list-polling.spec.js`. (RF-22, RF-24)
      Hecho cuando: el test falla si se quita el bloque `list` de la respuesta
      de `sync` o la guarda `if (data.list)` del cliente.

## Cantidad editable (cambio de alcance)

_Extensión de alcance registrada en `§ Clarificaciones` de `spec.md` ("Fase 8")
y en `plan.md`: la cantidad ya funciona en backend desde spec 001, aquí solo se
le abre entrada en la interfaz. **Ninguna de estas tareas toca backend.**_

- [x] T22. `list.blade.php`: input de cantidad opcional en el formulario de alta,
      **compartiendo la segunda fila con el precio** (T12), `type="text"`,
      `maxlength="50"`, label `sr-only` como los demás campos, `name="quantity"`
      e `id` propio; el chip de cantidad del `<ul>` pre-hidratado ya existe y no
      se toca. (RF-26)
      Hecho cuando: `ListPageTest` afirma además de `id="new-item"` que el alta
      tiene el input de cantidad, y `php artisan test` queda en verde — un campo
      vacío no llega al payload, así que los 5 e2e que usan `#new-item` siguen
      igual.
- [x] T23. `resources/js/list.js`: `addItem()` lee el nuevo input, lo recorta y
      agrega `quantity` al payload solo si queda distinto de `''` (mismo trato
      que `added_by`), limpiando también ese campo tras un alta exitosa. Sin
      normalización extra en cliente: el servidor ya recorta, vacía a `null` y
      valida `max:50`. (RF-26)
      Hecho cuando: `npm run build` compila y `npx playwright test` sigue en
      verde sin tocar selectores de los e2e existentes (los casos nuevos de la
      cantidad entran en T25).
- [x] T24. `resources/js/list.js` + `list.blade.php`: edición de la cantidad en
      la fila — `normalize()` inicializa `draftQuantity` con `item.quantity ?? ''`
      (no con `item.quantity`: `x-model` sobre `null` escribiría `"null"`);
      `startEdit()` carga los dos drafts; el chip de cantidad es clicable y llama
      a `startEdit()`; mientras `item.editing` la fila muestra el input de
      cantidad junto al de nombre, ambos con `data-edit-field="name" | "quantity"`;
      `commitEdit()` compara los dos drafts y manda en un único `PATCH` solo los
      campos que cambiaron, reemplazando el early-return que hoy sale cuando
      `name` no cambió; el cierre por `blur` del input de nombre pasa a
      `focusout` en el envoltorio que contiene los dos inputs, con chequeo
      `currentTarget.contains(relatedTarget)`; y `syncOnce()`
      conserva `draftQuantity` en la mezcla de un ítem que está en edición
      (`plan.md`, algoritmo 7). (RF-27)
      Hecho cuando: `npx playwright test` en verde, con `list.spec.js:84`
      acotado por `data-edit-field` (regla 2 de arriba: se arregla en la misma
      tarea, conservando el assert `Object.keys(...) === ['name']`, no
      relajándolo).
- [x] T25. `tests/e2e/list.spec.js`: regresiones de la cantidad — alta con
      cantidad muestra el chip en la fila y alta sin cantidad no deja chip;
      editar solo la cantidad manda un `PATCH` con exactamente `['quantity']`
      dejando nombre y estado comprado intactos; mover el foco del nombre a la
      cantidad no manda ningún `PATCH`; y la cantidad que el usuario está
      escribiendo no se pisa cuando llega una sincronización de otra página
      (patrón de dos páginas de `list-polling.spec.js`). (RF-26, RF-27, RF-28)
      Hecho cuando: el test de edición falla si `commitEdit` vuelve a salir
      temprano cuando `name` no cambió, y el de sincronización falla si se
      quita `draftQuantity` de la mezcla del poll.

## Cierre de la feature

_Checkboxes sueltos, fuera de la numeración `Tn` — no se implementan con
`/sdd:implement`._

- [x] Suite completa verde: `php artisan test` y `npx playwright test` sin
      fallos, y `vendor/bin/pint --test` sin cambios pendientes.
- [x] Verificación de todos los RF: `/sdd:validate` →
      `specs/002-precios-y-moneda/validation.md` (recorrido RF por RF +
      veredicto).
- [x] `docs/roadmap.md`: la feature 002 anotada como en curso con su rango `Tn`
      mientras avanzaba, y movida a "Hecho ✅" por `/sdd:validate`.
- [x] CHANGELOG: entrada de la feature al cerrarla (versión siguiente a `1.0.0`).
- [ ] Verificación manual en dos celulares: anotar un precio revisando el total
      antes de pagar, cambiar la moneda viendo que el otro celular se actualiza
      solo, y anotar la cantidad de un ítem en un celular a 320 px de ancho
      viéndola llegar al otro.
- [x] Convenciones descubiertas durante la implementación anotadas en
      `AGENTS.md` sobre la marcha, no en el cierre.

## Cobertura RF → tarea

| RF                       | Tareas                                            |
| ------------------------ | ------------------------------------------------- |
| RF-1                     | T0, T1, T4, T12, T14, T19                         |
| RF-2                     | T0, T1, T8                                        |
| RF-3                     | T1, T5, T14, T19                                  |
| RF-4                     | T0, T1, T5, T8, T14, T19                          |
| RF-5                     | T0, T4                                            |
| RF-6                     | T4, T7                                            |
| RF-7                     | T4, T7, T14, T19                                  |
| RF-8                     | T8, T12, T14, T19                                 |
| RF-9                     | — (definición en `§ Clarificaciones`, sin código) |
| RF-10                    | T2, T3                                            |
| RF-11                    | T6, T7, T9, T10, T13, T15, T19                    |
| RF-12                    | T6, T7                                            |
| RF-13                    | T2, T10, T12                                      |
| RF-14                    | T12, T13                                          |
| RF-15                    | T16, T19                                          |
| RF-16                    | T12, T16, T19                                     |
| RF-17                    | T16, T19                                          |
| RF-18                    | T16                                               |
| RF-19                    | T16                                               |
| RF-20                    | T16, T19                                          |
| RF-21                    | T11                                               |
| RF-22                    | T10, T11, T15, T17, T21                           |
| RF-23                    | T9, T11                                           |
| RF-24                    | T17, T21                                          |
| RF-25                    | T18, T20                                          |
| RF-26                    | T22, T23, T25                                     |
| RF-27                    | T24, T25                                          |
| RF-28                    | T24, T25                                          |
| RNF formato de importes  | T16, T19                                          |
| RNF precisión aritmética | T0, T1, T16, T19                                  |
| RNF sincronización (5 s) | T11, T17, T21                                     |
| RNF límite de peticiones | — (sin tarea: se reutiliza `writes`)              |
| constitución 8 (idioma)  | T7                                                |
