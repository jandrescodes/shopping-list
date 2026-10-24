# Plan técnico — Spec 004

## Estado de partida del repo

Sin saneamiento pendiente: specs 001-003 cerradas, release 1.3.0 desplegado, baseline `212 Pest + 58 Playwright` (spec
003, validation). T0 = primera prueba.

Hallazgos del código que condicionan el plan (leídos, no supuestos):

- El orden vive en un solo lugar del servidor, `Item::scopeOrderedActive` (`is_purchased, created_at, id`), que usan
  `show`, `sync` (carga completa y delta), la vista SSR de `/l/{slug}` y `duplicate`; el cliente lo replica en
  `orderItems` (`list.js:75`, por `id`).
- `ItemResource` no lleva posición ni fecha; `ShoppingListResource` y el bloque `list` del sync llevan `name` y
  `currency` (`ItemController::sync`).
- `ListVersion::write` bloquea la fila de la lista, sube la versión una vez y sella cada `Item` devuelto. El delta de
  sync solo devuelve ítems con `version > cursor`: **mover un ítem y cambiarle una sola fila basta para que llegue a los
  demás dispositivos**.
- Deshacer un borrado **recrea** el ítem con `POST` (+ `PATCH` si estaba comprado) y id nuevo (`list.js` `undoRemove`);
  no hay forma de que conserve su lugar sin que el servidor sepa dónde ponerlo (RF-4a).
- `price` es `decimal(10,2)`, con `NormalizesPrice` (trait atado al campo `price`) y regex `^\d{1,8}(\.\d{1,2})?$`.
  `list.js` ya calcula `pendingTotalCents` y `formattedPendingTotal`; `#live-total` solo se muestra con
  `hasPendingPrice` (spec 002 RF-17).
- Moneda y nombre se editan inline desde el menú de opciones (`#list-menu`, `#currency-chip`, `renameCurrency`): el
  presupuesto replica ese patrón.
- Alpine está pineado en `3.17.1`; la última publicada es `3.17.5`. `@alpinejs/sort` depende de `sortablejs ^1.15.2` y
  existe para `3.17.1`.
- `public/sw.js` está en `CACHE_VERSION = 'v4'`; el HTML de `/` es cache-first y apunta a assets con hash de `/build/*`.
- El 404 uniforme corre antes que el binding de ruta: un `{item}` borrado o ajeno ya da 404 en `PATCH`/`DELETE`.

## Estructura de módulos

- `database/migrations/…_add_position_to_items_table.php` → **nueva**: `items.position`, con relleno (RF-9).
  `…_add_budget_to_shopping_lists_table.php` → **nueva**: `shopping_lists.budget` (RF-20 a RF-22).
- `app/Models/Item.php` → `position` en `$fillable`; `scopeOrderedActive` pasa a `is_purchased, position, id` (RF-1,
  RF-2a, RF-6, RF-9).
- `app/Models/ShoppingList.php` → lógica de dominio (constitución 9): `nextPosition()`,
  `positionBefore(?int $beforeId)`, `moveItem(Item, ?int $beforeId)`, `renumberItems()`, y `duplicate()` copiando
  `position` y `budget` (RF-1, RF-4, RF-4a, RF-8a, RF-31). `budget` en `$fillable`.
- `app/Http/Controllers/Api/ItemController.php` → `store` acepta `before_id` opcional (RF-4a); `move` nuevo (RF-1, RF-7,
  RF-8, RF-8a).
- `app/Http/Requests/MoveItemRequest.php` → **nuevo**: valida `before_id`. `StoreItemRequest` suma `before_id`;
  `UpdateListRequest` suma `budget` (constitución 9).
- `app/Http/Requests/Concerns/NormalizesPrice.php` → recibe el nombre del campo (`normalizePrice('budget')`) para no
  duplicar la normalización de coma y vacío (RF-20, RF-23).
- `app/Http/Resources/ItemResource.php` → añade `position`. `ShoppingListResource.php` → añade `budget`. El bloque
  `list` de `sync` incluye `budget` (RF-6, RF-28).
- `app/Http/Controllers/Api/ShoppingListController.php` → `update` ya sube versión; solo cambia lo que
  `UpdateListRequest` deja pasar.
- `routes/api.php` → una ruta nueva bajo `throttle:writes`: `PATCH /lists/{list}/items/{item}/position`.
- `resources/js/list.js` → `orderItems` por `is_purchased, position, id`; reordenar (arrastre, subir/bajar, cola de
  envío agrupada, anuncio accesible); autocompletado; presupuesto (cálculo de exceso, edición inline); `undoRemove`
  envía `before_id` (RF-1 a RF-30).
- `resources/views/list.blade.php` → asa de arrastre y botones subir/bajar por ítem pendiente, panel de sugerencias bajo
  `#new-item`, entrada "Presupuesto" en el menú, `#budget-line`, `#budget-alert` y formulario `#budget-input`.
- `package.json` / `package-lock.json` → `@alpinejs/sort@3.17.1` exacto (D2).
- `public/sw.js` → `CACHE_VERSION` a `v5`.
- Docs: `docs/roadmap.md`, `docs/schema.md`, `docs/app-flow.md`, `AGENTS.md` (ruta nueva, convención de `position`,
  clases reservadas), `CHANGELOG.md` (`[1.4.0]`).

## Modelo de datos

| Tabla            | Cambio                                                 | Detalle                                                                                                                                                                   |
|------------------|--------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `items`          | `position` `BIGINT` con signo, `NOT NULL`, sin default | Una sola secuencia por lista (RF-2a), con huecos de 1024. Índice `(shopping_list_id, is_purchased, position)` reemplaza a `(shopping_list_id, is_purchased, created_at)`. |
| `shopping_lists` | `budget` `DECIMAL(10,2)` `NULL`                        | Mismo formato que `items.price`. `NULL` = sin presupuesto (RF-21). `0` es válido.                                                                                         |

Migración de `position`: (1) añadir la columna nullable; (2) rellenar con un `UPDATE … JOIN` sobre
`ROW_NUMBER() OVER (PARTITION BY shopping_list_id ORDER BY created_at, id) * 1024`, incluidas las lápidas (conservan su
número, no importa); (3) pasarla a `NOT NULL` sin default. No se usa `ADD … DEFAULT 0` porque rellenaría todo con 0 y
desordenaría la lista en silencio (lección de Laravel de `AGENTS.md`). `down()` quita la columna y restaura el índice.

Sin tabla de historial ni de sugerencias (constitución 7): el autocompletado se calcula de los ítems cargados.

## Algoritmos no triviales

### Posición relativa (`positionBefore`, RF-1, RF-4, RF-4a, RF-8a)

Una posición se pide siempre como "justo antes del ítem `B`" o "al final". Dentro del bloqueo de `ListVersion::write`:

1. `B` = ítem **activo** de esa lista con id `before_id`; si no se pidió, no existe o no es de la lista → al final.
2. Al final: `max(position)` de los activos (sin el ítem movido) + 1024; lista vacía → 1024.
3. Antes de `B`: `prev` = mayor `position` activa menor que `B.position` (sin el ítem movido). Nueva =
   `floor((prev + B.position) / 2)`; sin `prev`, `B.position − 1024`.
4. Si la nueva coincide con `prev` o con `B.position` (hueco agotado) → **renumerar**: todos los activos de la lista, en
   orden, con el ítem movido en su lugar, a pasos de 1024. Es la única operación que toca muchas filas; sella todas con
   la versión nueva y ocurre rara vez (hace falta partir ~10 veces el mismo hueco).

Bordes: `before_id` = el propio ítem, o el ítem ya está justo antes de `B` → no cambia nada y no sube la versión;
`before_id` borrado o ajeno → al final (no revela existencia); posiciones empatadas se desempatan por `id`; un `B`
comprado en el medio de pendientes es válido (la secuencia es única, RF-2a); varios ítems agregados por otro dispositivo
van al final con `max + 1024`, así que ni se pierden ni se reordenan entre sí (RF-8a).

### Mover (`move`, RF-1, RF-7, RF-8)

1. `{item}` borrado o de otra lista → 404 uniforme (ya lo da el binding); el cliente lo trata en silencio: no muestra
   error y fuerza una sincronización (RF-8).
2. Ítem comprado → `200` con el ítem sin cambios y sin subir la versión (RF-8: "cambió de grupo").
3. `ListVersion::write`: calcula la posición como arriba, guarda y devuelve el ítem (o todos si renumeró). Una subida de
   versión por operación (RF-6).
4. Gana el último en llegar (RF-7): no hay chequeo de versión del cliente.

### Cliente: reordenar, agrupar pulsaciones y sync (RF-1, RF-5 a RF-8)

- El orden local se aplica **al instante** (a diferencia del resto de escrituras, que no son optimistas), porque el
  arrastre y las pulsaciones seguidas de subir/bajar lo exigen.
- Cada ítem movido calcula `before_id` = el siguiente **pendiente** tras la posición final (o `null` si queda último).
  Las pulsaciones sobre el mismo ítem reinician un temporizador de ~400 ms y se envía una sola `PATCH` con el destino
  final. Las peticiones salen una a una (cola), para que no lleguen desordenadas.
- Mientras un ítem tiene un movimiento pendiente o en vuelo, `syncOnce` conserva su posición local (como ya hace con
  `editing`); al responder la `PATCH`, se adopta la posición del servidor.
- Fallo (red, 429, 404): se vuelve al orden del servidor con el aviso de error existente, salvo el 404 de ítem, que es
  silencioso (RF-8).
- Anuncio accesible tras cada movimiento: región `role="status"` con "<nombre>: posición n de m" (RF-5).

### Autocompletado (RF-11 a RF-19)

Función pura en `list.js` sobre `this.items` (ya cargados; sin peticiones):

1. Clave de comparación = texto en minúsculas y sin marcas diacríticas (`normalize('NFD')` + eliminar `\p{M}`).
2. Con `clave(escrito).length ≥ 2`, candidatos = ítems cuya clave **contiene** lo escrito.
3. Agrupar por clave y quedarse con el nombre del ítem de mayor `id` (el más reciente, RF-12); descartar el grupo cuya
   clave es igual a la escrita (RF-16).
4. Ordenar por: empieza por lo escrito primero, luego alfabético; cortar a 5 (RF-11).
5. Elegir una sugerencia solo completa el campo `#new-item` (RF-14); Enter sin sugerencia activa agrega lo escrito
   (RF-15). Sin candidatos → el panel no se renderiza (RF-19).

Patrón ARIA de combobox con lista (`role="combobox"`, `aria-expanded`, `aria-activedescendant`, `role="listbox"`),
flechas, Enter y Esc, y filas de 44 px (RF-18).

### Presupuesto (RF-20 a RF-30)

- Guardado: `PATCH /lists/{list}` con `budget` (`null` o `""` lo quita). Pasa por `ListVersion::write`, igual que nombre
  y moneda (RF-28).
- Cliente: `budgetCents` desde el decimal del servidor con el mismo helper de precios;
  `excessCents = pendingTotalCents − budgetCents`; el aviso aparece solo si `excessCents > 0` (igual al tope no es
  exceso, RF-25) y se reevalúa porque depende de `items`, `budget` y `currency` reactivos, incluidos los cambios por
  sync (RF-27).
- Sin total pendiente (`!hasPendingPrice`): `#budget-line` se muestra sola y no hay aviso (RF-29).
- El cambio de moneda no toca `budget` (RF-30).

## Decisiones técnicas

| #   | Decisión                                                                                                                                                                                      | Alternativa descartada y por qué                                                                                                                                                                                                                |
|-----|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| D1  | `position` entera con huecos de 1024 y renumeración ocasional.                                                                                                                                | Renumerar la lista entera en cada movimiento: tocaría hasta N filas y llevaría N ítems en cada delta. Columna `float`: acumula error de precisión y no tiene renumeración definida.                                                             |
| D2  | Arrastre con `@alpinejs/sort@3.17.1` (exacto, igual a Alpine), que trae `sortablejs` como dependencia transitiva fijada por el lockfile. Necesita smoke test de arrastre y asa en Playwright. | HTML5 drag-and-drop nativo: no funciona en táctil. Implementación propia con pointer events: más código, más riesgo con el scroll del celular (constitución 10). Constitución 1 exige justificarla: sin ella, el mejor sustituto es más frágil. |
| D3  | Mover y restaurar piden "justo antes de `before_id`" (relativo), no un índice ni la lista completa.                                                                                           | Enviar el orden completo: un ítem agregado por otro dispositivo no vendría en el payload y se perdería o reordenaría (RF-8a); además exigiría un tope de tamaño. La operación de un solo ítem acota el cuerpo por diseño.                       |
| D4  | Una ruta propia `PATCH …/items/{item}/position`.                                                                                                                                              | Meter `position` en `PATCH /items/{item}`: mezcla edición de campos (que no pisa campos ajenos) con una operación relativa y obligaría a validar `before_id` en `UpdateItemRequest`.                                                            |
| D5  | `before_id` opcional también en `store`, para restaurar el borrado en su lugar.                                                                                                               | Un segundo `PATCH` de posición tras recrear: dos escrituras y dos subidas de versión por deshacer, con un instante visible en el lugar equivocado.                                                                                              |
| D6  | Orden local optimista solo para reordenar, con cola y agrupación de pulsaciones.                                                                                                              | Esperar la respuesta en cada paso: mover un ítem 15 lugares costaría 15 peticiones de las 120/min y podrían llegar desordenadas (hallazgo de la revisión).                                                                                      |
| D7  | Autocompletado 100 % cliente sobre `this.items`.                                                                                                                                              | Endpoint de sugerencias: peticiones por tecla contra `writes`/`sync`, y obligaría a consultar lápidas (constitución 7).                                                                                                                         |
| D8  | Presupuesto como columna `budget` nullable en `shopping_lists`, editado por `PATCH /lists/{list}`.                                                                                            | Tabla aparte de presupuestos: una fila por lista, sin historial permitido (constitución 7).                                                                                                                                                     |
| D9  | Edición del presupuesto en el menú de opciones con el patrón inline de moneda: entrada "Presupuesto" con chip, formulario `#budget-input`.                                                    | Campo permanente junto al total: añade ruido a una función opcional; el menú es el patrón establecido (1.2.1 y spec 003 D9).                                                                                                                    |
| D10 | Aviso de exceso `#budget-alert` con `bg-orange-50` y texto, no solo color.                                                                                                                    | Reutilizar `bg-amber-50`/`bg-red-50`: ambos están reservados para e2e (`AGENTS.md`).                                                                                                                                                            |
| D11 | `#budget-line` y `#budget-alert` solo existen en la vista hidratada (`ready`).                                                                                                                | Duplicarlos en SSR como el total (`#live-total`): dos copias que divergen y sin necesidad, el presupuesto es secundario al primer pintado.                                                                                                      |
| D12 | `CACHE_VERSION` a `v5`.                                                                                                                                                                       | No subirla: `/` es cache-first y el bundle de `list.js` cambia de hash con la dependencia nueva; el shell cacheado apuntaría a assets viejos.                                                                                                   |
| D13 | Alpine se queda en `3.17.1`.                                                                                                                                                                  | Subir a `3.17.5` aquí: es un cambio ajeno a la spec; el plugin debe coincidir con el core, y el bump se verifica y se hace aparte.                                                                                                              |

## Contrato de interfaz

Todas sin autenticación (constitución 4), JSON siempre, 404 uniforme.

| Método y ruta                                   | Entrada                                                                   | Éxito                                                                                                                         | Errores                                                                                         |
|-------------------------------------------------|---------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------|
| `PATCH /api/lists/{list}/items/{item}/position` | `before_id`: entero ≥ 1 o `null` (obligatorio que esté presente)          | `200` con el `ItemResource` del ítem movido (con `position` y `version`)                                                      | `422` validación; `404` lista o ítem inexistente/borrado/ajeno; `429` (`writes`, `Retry-After`) |
| `POST /api/lists/{list}/items` (ya existe)      | + `before_id`: entero ≥ 1 o `null`, opcional                              | `201` igual que hoy; si se dio `before_id` activo, el ítem queda justo antes; en otro caso al final                           | igual que hoy                                                                                   |
| `PATCH /api/lists/{list}` (ya existe)           | + `budget`: decimal ≥ 0 con regex de `price`, o `null`/`""` para quitarlo | `200` `ShoppingListResource` con `budget`                                                                                     | `422` con mensaje en español; igual que hoy                                                     |
| `GET /api/lists/{list}` y `GET …/items`         | sin cambios de entrada                                                    | `ItemResource` suma `position`; `ShoppingListResource` y el bloque `list` del sync suman `budget` (cadena `"12.50"` o `null`) | igual que hoy                                                                                   |

Cliente (`list.js`): `moveItem(item, direction)` y el manejador de `x-sort` aplican el orden local y encolan;
`flushMoves()` envía; `suggestions` (getter) y `pickSuggestion(name)`; `budgetCents`, `excessCents`,
`saveBudget(value)`; `undoRemove` añade `before_id` = el id del siguiente ítem activo que había al borrar.

## Riesgos

| Riesgo                                                                                                                                       | Mitigación / por qué se acepta                                                                                                                                                                                                  |
|----------------------------------------------------------------------------------------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `x-sort` trata como hermanos a los comprados, que no se pueden mover (RF-2).                                                                 | Primera tarea es un spike: marcar solo los pendientes como ítems ordenables y comprobar que el índice que entrega el plugin cuenta bien; si no, partir la lista en dos `<ul>`, conservando `#client-item-list` para el locator. |
| Dependencia nueva con `sortablejs` transitivo.                                                                                               | Versión exacta, lockfile, `npm audit --omit=dev` ya corre en CI, smoke test de arrastre y de asa (regla de dependencias de `AGENTS.md`).                                                                                        |
| Arrastre rompe el scroll táctil.                                                                                                             | Asa dedicada (`x-sort:handle`); verificar en un viewport táctil de Playwright.                                                                                                                                                  |
| Restaurar un borrado cuando su "siguiente" también fue borrado: cae al final, no "junto al más cercano" como dice el caso límite de la spec. | Se acepta y se pide ajustar ese caso límite de la spec a "al final si el vecino ya no existe". Alternativa mayor: enviar varios vecinos, descartada por complejidad.                                                            |
| RF-8 dice "sin error"; un ítem borrado responde 404.                                                                                         | El cliente lo trata en silencio y resincroniza; el usuario no ve error. El 404 se conserva por consistencia con `PATCH`/`DELETE` y con el 404 uniforme.                                                                         |

**Decisión del spike (T12): una sola lista.** `#client-item-list` conserva todas las filas. Los pendientes llevan
`data-pending` y `draggable: '[data-pending]'` en `x-sort:config`; los comprados no tienen asa (`x-show`) ni son
arrastrables. El índice que entrega el plugin (`e.newIndex`) cuenta todas las filas, pero como los pendientes siempre van
primero coincide con el índice entre pendientes; un soltar sobre la zona de comprados da un índice mayor, que el
manejador de T14 recorta al número de pendientes. No hace falta partir en dos `<ul>`.
| Renumeración sella muchas filas y agranda un delta.                                                                                          | Ocurre rara vez, acotada al tamaño de la lista (cientos de filas como mucho) en una sola subida de versión.                                                                                                                     |
| Migración con `UPDATE … JOIN` y `ROW_NUMBER()` en producción.                                                                                | MariaDB 11.8 y MySQL 8 de tests lo soportan; se prueba con datos sembrados antes del `NOT NULL` y se despliega con el flujo normal.                                                                                             |
| Un movimiento en vuelo y un sync que trae otra posición del mismo ítem.                                                                      | `syncOnce` conserva la posición local de ítems con movimiento pendiente y adopta la del servidor al responder.                                                                                                                  |
| Locators e2e rotos por el aviso nuevo.                                                                                                       | D10 + `id` propios; verificar que los `#list-app .bg-*` existentes siguen resolviendo un solo elemento.                                                                                                                         |
| `ApiRoutesTest` afirma el número exacto de rutas.                                                                                            | Se actualiza a trece en la tarea que añade la ruta.                                                                                                                                                                             |

## Estrategia de verificación

Puerta de cada tarea: `php artisan test`, y `npx playwright test` si toca `resources/js/`.

- **Pest** (MySQL real):
    - `ItemPositionMigrationTest`: tras migrar, los ítems existentes (activos y lápidas) conservan su orden de creación
      y ninguno queda en 0; comportamiento, no `Schema::getColumns` (lección de Laravel).
    - `ItemControllerMoveTest`: antes de `B`, al final, `before_id` nulo/ajeno/borrado, sin cambio no sube versión, ítem
      comprado ignorado, ítem borrado 404, una sola subida de versión, delta de sync trae el ítem con `position`,
      renumeración cuando se agota el hueco (sella todos), ítems agregados por otro dispositivo no se pierden, `422` de
      validación, `429` con `Retry-After`.
    - `ItemControllerStoreTest`: `before_id` válido, inexistente y nulo; nuevo ítem al final.
    - `ListRequestTest` / `ShoppingListControllerUpdateTest`: `budget` válido, `0`, quitar con `null` y `""`, coma
      decimal, negativo, no numérico, demasiados dígitos; sube versión; no altera `currency`.
    - `ShoppingListDuplicateTest`: la copia conserva `position` y `budget`; los comprados de la original quedan
      pendientes en su lugar.
    - `ItemResourceTest` y sync: `position` en carga completa y delta, `budget` en el bloque `list`.
    - `ListPageTest`: la vista SSR muestra el orden por `position`.
    - `ApiRoutesTest`: trece rutas, sin auth; `RateLimitingTest`: `position` consume `writes`.
- **Playwright** (`list-order.spec.js`, `list-suggest.spec.js`, `list-budget.spec.js`):
    - Orden: arrastre con asa y con teclado (subir/bajar), sin controles con menos de dos pendientes, comprados fuera
      del arrastre, marcar y desmarcar conserva la posición, agrupación de pulsaciones (una petición), orden visible en
      un segundo contexto tras el polling, deshacer un borrado vuelve a su lugar, reinicio conserva el orden, texto
      exportado en orden manual.
    - Sugerencias: mínimo 2 caracteres, tope de 5, sin tildes ni mayúsculas, deduplicación, no sugiere ítems borrados,
      elegir solo completa el nombre, ignorar y agregar tal cual, teclado.
    - Presupuesto: fijar, quitar, `0`, exceso y su desaparición al marcar, igual no avisa, sin total muestra solo el
      presupuesto, cambio de moneda sin conversión, propagación a otro dispositivo, copia por duplicado.
- **UI**: axe-core a 375 y 1280 px sin violaciones (incluido el aviso naranja, el panel de sugerencias y las asas),
  pasada de teclado completa, targets de 44 px, arrastre en viewport táctil sin romper el scroll.
- **Cierre**: `vendor/bin/pint`, `npm audit --omit=dev`, `CACHE_VERSION` en `v5`, `docs/roadmap.md`, `docs/schema.md`,
  `docs/app-flow.md`, `AGENTS.md` y `CHANGELOG.md` actualizados, `/sdd:validate`.

## Cobertura de RF

| RF                         | Parte del plan que lo cubre                                                                                             |
|----------------------------|-------------------------------------------------------------------------------------------------------------------------|
| RF-1, RF-6, RF-7           | `ItemController::move` + `ShoppingList::moveItem` + `ListVersion::write`; cliente `moveItem`/cola                       |
| RF-2, RF-2a, RF-3          | secuencia única `position` + `orderedActive` por `is_purchased, position, id`; solo pendientes ordenables en el cliente |
| RF-4, RF-8a                | `positionBefore`: al final con `max + 1024` en `store` y para ítems ajenos                                              |
| RF-4a                      | `before_id` en `store` + `undoRemove` (riesgo del vecino borrado)                                                       |
| RF-5                       | `x-sort` con asa, botones subir/bajar, región de anuncio                                                                |
| RF-8                       | `move` ignora comprados y trata el 404 en silencio en el cliente                                                        |
| RF-9                       | migración con relleno por `ROW_NUMBER()`                                                                                |
| RF-10                      | controles con `x-show` según pendientes ≥ 2                                                                             |
| RF-11 a RF-19              | getter `suggestions` y panel combobox en `list.js`/`list.blade.php`                                                     |
| RF-20 a RF-23              | `budget` + `UpdateListRequest` + `NormalizesPrice`                                                                      |
| RF-24, RF-25, RF-26, RF-29 | `#budget-line`, `#budget-alert`, `excessCents` (sin bloquear nada)                                                      |
| RF-27, RF-28               | reactividad de `list.js` + bloque `list` del sync con `budget`                                                          |
| RF-30                      | `budget` no se toca al cambiar `currency`                                                                               |
| RF-31                      | `ShoppingList::duplicate()` copia `position` y `budget`                                                                 |
| RF-32, RF-33, RF-34        | `exportText` y reinicio usan el orden por `position`; el presupuesto no entra al texto                                  |
| RNF cotas                  | D3, D6 (cola y agrupación)                                                                                              |
| RNF avisos y clases        | D10                                                                                                                     |
| RNF dependencias           | D2, D13                                                                                                                 |
