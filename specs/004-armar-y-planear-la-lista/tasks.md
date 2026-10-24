# Tareas — Spec 004

Tareas de <30 min, ordenadas por dependencia. Cada una: sus RF, un checkbox y
una línea "Hecho cuando:" verificable. Derivadas de `plan.md`; los RF salen de
`spec.md`.

Puerta de verificación por tarea (`AGENTS.md`): `php artisan test` siempre;
`npx playwright test` cuando la tarea toque `resources/js/`; `vendor/bin/pint`
sobre lo tocado. Ninguna tarea se marca hecha en rojo.

Reglas que atraviesan todo el bloque:

- **`ApiRoutesTest` se actualiza en la misma tarea que añade la ruta** (T4),
  porque afirma el número exacto de rutas. Se conserva la exactitud de la
  aserción, nunca se relaja.
- **Escritura de tests primero**: en las tareas de backend y de cliente el test
  se escribe antes y se ve fallar por el motivo correcto antes de implementar.
- **Clases de fondo reservadas**: ningún elemento nuevo usa `bg-blue-50`,
  `bg-green-50`, `bg-gray-100`, `bg-amber-50`, `bg-sky-50`, `bg-teal-50` ni
  `bg-red-50`; cada componente lleva su `id` (`#budget-line`, `#budget-alert`,
  `#budget-input`, `#name-suggestions`).
- **Dependencia nueva**: `@alpinejs/sort` solo entra en T11, con versión exacta.

## Backend: orden de los ítems

- [x] T0. Baseline: ejecutar `php artisan test` y `npx playwright test` sin
  cambios y anotar los conteos de partida. (Estado de partida del plan)
  Hecho cuando: ambas suites pasan en verde y los conteos (esperados 212 Pest
  y 58 Playwright) quedan escritos en el mensaje de la tarea.
  Resultado: 212 Pest (1113 aserciones) y 58 Playwright en verde.

- [x] T1. Migración de `items.position` (`BIGINT` con signo, `NOT NULL`, sin
  default): añadir nullable, rellenar con `ROW_NUMBER() OVER (PARTITION BY
      shopping_list_id ORDER BY created_at, id) * 1024` (incluidas las lápidas),
  pasar a `NOT NULL` y cambiar el índice a `(shopping_list_id, is_purchased,
      position)`. `Item` recibe `position` en `$fillable` y un hook `creating`
  que, si no trae posición, la pone al final (`max + 1024`, lista vacía
  1024); `ItemFactory` sigue funcionando. Test `ItemPositionMigrationTest`:
  tras migrar con datos sembrados, el orden de creación se conserva y
  ninguna fila queda en 0. (RF-4, RF-9, RF-8a)
  Hecho cuando: `php artisan test --filter=ItemPositionMigrationTest` pasa,
  el test falló antes de implementar, y `php artisan test` completo sigue
  verde.

- [x] T2. `Item::scopeOrderedActive` pasa a `is_purchased, position, id`;
  `ItemResource` añade `position`. Tests: `show`, la carga completa y el
  delta de `sync` devuelven `position` y respetan el orden; la vista SSR de
  `/l/{slug}` lista en el orden por posición (`ListPageTest`); los comprados
  conservan su posición al marcar y desmarcar. (RF-2, RF-2a, RF-3, RF-6)
  Hecho cuando: `php artisan test --filter=ItemResourceTest`,
  `--filter=ItemControllerSyncTest` y `--filter=ListPageTest` pasan con esos
  casos.

- [x] T3. `ShoppingList::positionBefore(?int $beforeId, ?Item $moving)` y
  `moveItem(Item, ?int $beforeId)`: antes de un vecino activo, al final si no
  hay `before_id` o no existe/no es de la lista, no-op si ya está en ese
  lugar, y `renumberItems()` cuando el hueco se agota (pasos de 1024, sella
  todos). Test `ShoppingListMoveItemTest` a nivel de modelo. (RF-1, RF-8a)
  Hecho cuando: `php artisan test --filter=ShoppingListMoveItemTest` pasa con
  los casos: antes de vecino, al final, vecino borrado o ajeno, mismo ítem,
  sin cambio y hueco agotado.

- [x] T4. `PATCH /lists/{list}/items/{item}/position`: `MoveItemRequest`
  (`before_id` presente, entero ≥ 1 o `null`), ruta bajo `throttle:writes`,
  `ItemController::move` vía `ListVersion::write`; devuelve el `ItemResource`.
  Test `ItemControllerMoveTest` (mueve y persiste, una sola subida de
  versión, sella el ítem, 422 con `before_id` inválido, 404 uniforme para
  lista inexistente); `ApiRoutesTest` pasa a trece rutas. (RF-1, RF-6, RF-7)
  Hecho cuando: `php artisan test --filter=ItemControllerMoveTest` y
  `--filter=ApiRoutesTest` pasan.

- [x] T5. Casos límite de mover: ítem comprado se ignora con `200` y sin subir
  la versión, ítem borrado responde 404, sin cambio no sube la versión, el
  delta (`cursor` previo) trae el ítem con su `position`, la renumeración
  sella todos los ítems con la misma versión, ítems agregados por otro
  dispositivo no se pierden ni se reordenan entre sí, y `RateLimitingTest`
  confirma que la ruta consume `writes` con `Retry-After`. (RF-7, RF-8,
  RF-8a)
  Hecho cuando: `php artisan test --filter=ItemControllerMoveTest` y
  `--filter=RateLimitingTest` pasan con esos casos.

- [x] T6. `POST /lists/{list}/items` acepta `before_id` opcional (`nullable`,
  entero ≥ 1) en `StoreItemRequest`; el controlador lo separa de lo que se
  guarda y coloca el ítem justo antes de ese vecino activo, o al final si
  falta, es nulo o ya no existe. Tests en `ItemControllerStoreTest`: con
  `before_id` válido, inexistente, nulo y ausente. (RF-4, RF-4a)
  Hecho cuando: `php artisan test --filter=ItemControllerStoreTest` pasa y el
  test falló antes de implementar.

- [x] T7. `ShoppingList::duplicate()` copia la `position` de cada ítem activo, de
  modo que la copia (con todos pendientes) conserva el orden manual de la
  original, comprados incluidos. Test en `ShoppingListDuplicateTest` con una
  lista reordenada y con comprados intercalados. (RF-31)
  Hecho cuando: `php artisan test --filter=ShoppingListDuplicateTest` pasa con
  el caso de orden y la lista original queda intacta.

## Backend: presupuesto

- [x] T8. Migración de `shopping_lists.budget` (`DECIMAL(10,2)` nullable);
  `ShoppingList` lo añade a `$fillable`; `ShoppingListResource` y el bloque
  `list` de `sync` lo devuelven como cadena `"12.50"` o `null`; `duplicate()`
  copia el presupuesto. Tests: sin presupuesto devuelve `null`, el sync lo
  trae, la copia lo hereda. (RF-21, RF-24, RF-28, RF-31)
  Hecho cuando: `php artisan test --filter=ShoppingListControllerShowTest`,
  `--filter=ItemControllerSyncTest` y `--filter=ShoppingListDuplicateTest`
  pasan con esos casos.

- [x] T9. `UpdateListRequest` acepta `budget` (`sometimes`, `nullable`, regex y
  máximo de `price`); `NormalizesPrice` recibe el nombre del campo para
  reutilizar la normalización de coma y vacío; mensajes en
  `lang/es/validation.php`. Tests en `ListRequestTest` y
  `ShoppingListControllerUpdateTest`: monto válido, `0`, coma decimal, quitar
  con `null` y con `""`, negativo, no numérico, demasiados dígitos, sube la
  versión una vez y no altera `currency` al cambiar solo el presupuesto ni al
  revés. (RF-20, RF-22, RF-23, RF-28, RF-30)
  Hecho cuando: `php artisan test --filter=ListRequestTest` y
  `--filter=ShoppingListControllerUpdateTest` pasan con esos casos.

## Dependencia y spike del arrastre

- [x] T10. Verificar las versiones antes de pinear: leer `description` y
  `dependencies` de `@alpinejs/sort@3.17.1` en el registro, confirmar que
  coincide con `alpinejs@3.17.1` del `package.json`, y anotar la versión
  más reciente de ambos. (Constitución 1; D2, D13)
  Hecho cuando: el resultado queda anotado en el mensaje de la tarea y la
  versión elegida es la de `alpinejs` pineada.
  Resultado (2026-10-24): `@alpinejs/sort@3.17.1` = «An Alpine plugin for
  drag sorting items on the page», MIT, única dependencia `sortablejs ^1.15.2`
  (hoy 1.15.x); `alpinejs@3.17.1` = «The rugged, minimal JavaScript framework»,
  MIT, dependencia `@vue/reactivity ~3.5.40`. Ambos publicados el 2026-08-31 y
  coinciden con el `alpinejs` pineado en `package.json`. Las últimas de ambos
  son 3.17.5 (2026-10-20); el bump queda fuera de alcance. Versión elegida:
  `@alpinejs/sort@3.17.1`.

- [x] T11. `npm i --save-exact @alpinejs/sort@3.17.1`, registrar el plugin en
  `list.js` antes de `Alpine.start()` y `npm run build`. (RF-5)
  Hecho cuando: `package.json` fija `"@alpinejs/sort": "3.17.1"` sin rango,
  `npm run build` termina sin errores, `npm audit --omit=dev` no reporta
  vulnerabilidades nuevas y `php artisan test` + `npx playwright test`
  siguen verdes.

- [x] T12. Spike: en la vista, marcar solo los ítems pendientes como ordenables (`x-sort:item`) con asa dedicada
  (`x-sort:handle`) y comprobar que el
  índice que entrega el plugin cuenta solo pendientes. Si cuenta mal, partir
  la lista en dos `<ul>` conservando `#client-item-list` para pendientes.
  Test Playwright de humo `list-order.spec.js`: arrastrar por el asa mueve
  un ítem y un comprado no se arrastra. (RF-2, RF-5)
  Hecho cuando: `npx playwright test list-order.spec.js` pasa el caso de
  humo y la decisión (una o dos listas) queda anotada en el plan.

## Cliente: reordenar

- [x] T13. `list.js`: `normalize` conserva `position` y `orderItems` ordena por
  `is_purchased, position, id`; `syncOnce` aplica el orden del servidor.
  Playwright: un reordenamiento hecho por API en otro contexto aparece en el
  primer contexto tras el polling, y marcar/desmarcar conserva la posición. (RF-1, RF-2a, RF-3, RF-6, RF-34)
  Hecho cuando: `npx playwright test list-order.spec.js list-polling.spec.js`
  pasa y el test falló antes de implementar.

- [x] T14. Arrastre completo: el manejador de `x-sort` aplica el orden local,
  calcula `before_id` (siguiente pendiente tras el destino o `null`) y envía
  la `PATCH`; cuando hay menos de dos pendientes no se muestran asa ni
  botones; región `role="status"` anuncia "<nombre>: posición n de m". (RF-1, RF-5, RF-10)
  Hecho cuando: `npx playwright test list-order.spec.js` pasa los casos de
  arrastre con persistencia tras recargar, sin controles con un solo
  pendiente y anuncio accesible.

- [x] T15. Botones "subir" y "bajar" por ítem pendiente con `aria-label` propio,
  targets de 44 px y cola de envío: las pulsaciones seguidas sobre un mismo
  ítem se agrupan en una sola `PATCH` (~400 ms) y las peticiones salen una a
  una; `syncOnce` conserva la posición local de un ítem con movimiento
  pendiente; un 404 de ítem resincroniza sin mostrar error y un fallo de red
  o 429 vuelve al orden del servidor con el aviso existente. (RF-5, RF-7,
  RF-8)
  Hecho cuando: `npx playwright test list-order.spec.js` pasa los casos de
  teclado, de una sola petición tras varias pulsaciones, del ítem borrado
  por otro dispositivo y del 429.

- [x] T16. `undoRemove` envía `before_id` = id del siguiente ítem activo que
  había al borrar (guardado en el snapshot de deshacer); si ya no existe, el
  ítem vuelve al final de pendientes. Reiniciar compras y su deshacer
  conservan el orden. (RF-4a, RF-34)
  Hecho cuando: `npx playwright test list-order.spec.js list-weekly.spec.js`
  pasa el deshacer al mismo lugar, el deshacer con vecino borrado (al final) y
  el reinicio con orden conservado.

- [x] T17. El texto exportado sigue el orden manual (pendientes primero,
  comprados después) y no incluye el presupuesto. (RF-32, RF-33)
  Hecho cuando: `npx playwright test list-weekly.spec.js` pasa la comparación
  del texto copiado con una lista reordenada y con presupuesto fijado.

## Cliente: autocompletado

- [x] T18. Getter `suggestions` en `list.js`: clave sin mayúsculas ni tildes,
  mínimo 2 caracteres, coincidencia por contenido, deduplicación por clave
  quedándose con el ítem de mayor `id`, sin repetir lo ya escrito por
  completo, máximo 5, empieza-por primero; solo ítems activos. (RF-11, RF-12,
  RF-13, RF-16, RF-17, RF-19)
  Hecho cuando: `npx playwright test list-suggest.spec.js` pasa los casos de
  2 caracteres, tope de 5, tildes y mayúsculas, deduplicación, ítem borrado
  que no sugiere y sin coincidencias.

- [x] T19. Panel `#name-suggestions` bajo `#new-item` con patrón combobox (`role="combobox"`, `aria-expanded`,
  `aria-activedescendant`, `listbox`),
  flechas, Enter, Esc y toque, filas de 44 px; elegir una sugerencia completa
  solo el nombre y Enter sin sugerencia activa agrega lo escrito. (RF-14,
  RF-15, RF-18, RF-19)
  Hecho cuando: `npx playwright test list-suggest.spec.js` pasa los casos de
  elegir con teclado y con clic (cantidad y precio quedan vacíos), ignorar y
  agregar tal cual, y sin panel cuando no hay coincidencias.

## Cliente: presupuesto

- [x] T20. Menú de opciones: entrada "Presupuesto" con chip `#budget-chip` y
  formulario inline `#budget-input` (patrón de moneda): `list.js`
  `saveBudget(value)` envía `PATCH /lists/{list}` con `budget`, vacío lo
  quita y los errores de validación se muestran en español; el presupuesto
  llega a otro dispositivo por el sync. (RF-20, RF-21, RF-22, RF-23, RF-28)
  Hecho cuando: `npx playwright test list-budget.spec.js` pasa los casos de
  fijar, cambiar, quitar, valor inválido, `0` y propagación a un segundo
  contexto.

- [x] T21. `#budget-line` junto a `#live-total` (o sola si no hay total pendiente)
  y aviso `#budget-alert` con `bg-orange-50` y texto "Te pasas por <moneda>
  <importe>" cuando `pendingTotalCents > budgetCents`; igual al tope no avisa,
  no bloquea agregar, editar ni marcar, y se reevalúa tras agregar, editar,
  marcar, borrar, reiniciar, deshacer y sync; cambiar la moneda no convierte
  el monto. (RF-24, RF-25, RF-26, RF-27, RF-29, RF-30)
  Hecho cuando: `npx playwright test list-budget.spec.js` pasa los casos de
  exceso y su desaparición al marcar, igual al tope, sin total pendiente,
  reinicio de compras, cambio de moneda, y confirma que
  `#list-app .bg-amber-50`, `.bg-red-50` y `.bg-green-50` de los tests
  existentes siguen resolviendo un solo elemento.

- [x] T22. Duplicar y exportar con presupuesto: la copia nace con presupuesto y
  orden de la original. (RF-31, RF-33)
  Hecho cuando: `npx playwright test list-weekly.spec.js list-budget.spec.js`
  pasa el caso de duplicar una lista reordenada y con presupuesto.

## Cache y accesibilidad

- [x] T23. `public/sw.js`: `CACHE_VERSION` a `v5`; `ServiceWorkerTest` y el e2e
  de offline reflejan el cambio. (RNF; D12)
  Hecho cuando: `php artisan test --filter=ServiceWorkerTest` y
  `npx playwright test list-offline.spec.js` pasan.

- [x] T24. Pasada de accesibilidad: axe-core a 375 y 1280 px sobre la lista con
  asas, botones, panel de sugerencias abierto, presupuesto y aviso de exceso;
  pasada de teclado completa; arrastre en viewport táctil sin romper el
  scroll vertical; targets de 44 px y contraste. (RF-5, RF-18, RNF)
  Hecho cuando: axe no reporta violaciones en ninguno de los dos anchos y la
  pasada de teclado y la táctil quedan registradas en `list-order.spec.js`.

## Cierre de la feature

_Checkboxes sueltos, fuera de la numeración `Tn` — no se implementan con
`/sdd:implement`._

- [x] Suite completa verde: `php artisan test` y `npx playwright test` sin
  fallos, y `vendor/bin/pint --test` sin cambios pendientes.
- [x] Verificación de todos los RF: `/sdd:validate` →
  `specs/004-armar-y-planear-la-lista/validation.md` (recorrido RF por RF +
  veredicto).
- [x] `docs/roadmap.md`: la feature 004 anotada como en curso con su rango `Tn`
  mientras avanza, y movida a "Hecho ✅" por `/sdd:validate`; quitar del
  backlog "Reordenar ítems", "Autocompletado de ítems frecuentes" y
  "Presupuesto opcional por lista".
- [x] `docs/schema.md` con `items.position` y `shopping_lists.budget`;
  `docs/app-flow.md` regenerado con `/sdd:docs flow 004-armar-y-planear-la-lista`.
- [x] `AGENTS.md`: ruta `PATCH …/items/{item}/position`, convención de `position`
  con huecos y renumeración, `before_id` en el alta, `bg-orange-50` y los
  `id` nuevos reservados para e2e.
- [x] CHANGELOG: entrada `[1.4.0]` de la feature al cerrarla.
- [x] README: revisar si lista las funciones de la app y añadir las tres.
- [x] Verificación manual en dos celulares: reordenar en uno y verlo en el otro
  en ≤ 5 s, arrastrar con el dedo sin romper el scroll, borrar y deshacer en
  su lugar, autocompletar un nombre, fijar un presupuesto y verlo pasarse.
- [x] Convenciones descubiertas durante la implementación anotadas en
  `AGENTS.md` sobre la marcha, no en el cierre.

## Cobertura RF → tarea

| RF    | Tareas                  |
|-------|-------------------------|
| RF-1  | T3, T4, T12, T13, T14   |
| RF-2  | T2, T12, T13            |
| RF-2a | T2, T13                 |
| RF-3  | T2, T13                 |
| RF-4  | T1, T6                  |
| RF-4a | T6, T16                 |
| RF-5  | T11, T12, T14, T15, T24 |
| RF-6  | T2, T4, T13             |
| RF-7  | T4, T5, T15             |
| RF-8  | T5, T15                 |
| RF-8a | T1, T3, T5              |
| RF-9  | T1                      |
| RF-10 | T14                     |
| RF-11 | T18                     |
| RF-12 | T18                     |
| RF-13 | T18                     |
| RF-14 | T19                     |
| RF-15 | T19                     |
| RF-16 | T18                     |
| RF-17 | T18                     |
| RF-18 | T19, T24                |
| RF-19 | T18, T19                |
| RF-20 | T9, T20                 |
| RF-21 | T8, T20                 |
| RF-22 | T9, T20                 |
| RF-23 | T9, T20                 |
| RF-24 | T8, T21                 |
| RF-25 | T21                     |
| RF-26 | T21                     |
| RF-27 | T21                     |
| RF-28 | T8, T9, T20             |
| RF-29 | T21                     |
| RF-30 | T9, T21                 |
| RF-31 | T7, T8, T22             |
| RF-32 | T17                     |
| RF-33 | T17, T22                |
| RF-34 | T13, T16                |
