# Validación — Spec 002

## Recorrido RF por RF

| RF    | Resumen                                                    | Evidencia                                                                                                     | Veredicto |
| ----- | ---------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------- | --------- |
| RF-1  | Guarda el precio al crear un ítem                          | `tests/Feature/ItemControllerStoreTest.php`; `tests/e2e/list-price.spec.js`                                   | PASS      |
| RF-2  | Precio opcional, sin persistir `0.00` por ausencia         | `tests/Feature/StoreItemRequestTest.php`; `tests/Feature/ItemControllerStoreTest.php`; e2e de alta sin precio | PASS      |
| RF-3  | Guarda el precio al editar un ítem                         | `tests/Feature/ItemControllerUpdateTest.php`; `tests/e2e/list-price.spec.js`                                  | PASS      |
| RF-4  | Vaciar el precio lo deja en `null`                         | `tests/Feature/UpdateItemRequestTest.php`; e2e de limpieza de precio                                          | PASS      |
| RF-5  | Acepta importes no negativos dentro de las cotas           | `tests/Feature/StoreItemRequestTest.php`; `tests/Feature/UpdateItemRequestTest.php`                           | PASS      |
| RF-6  | Rechaza formatos, negativos y cotas inválidas              | `tests/Feature/StoreItemRequestTest.php`; `tests/e2e/list-price.spec.js`                                      | PASS      |
| RF-7  | Coma y punto producen el mismo monto                       | `tests/Feature/StoreItemRequestTest.php`; e2e `12,50`/`12.50`                                                 | PASS      |
| RF-8  | Muestra el precio solo cuando existe                       | `tests/e2e/list-price.spec.js`                                                                                | PASS      |
| RF-9  | El precio representa el total de la línea                  | Alcance explícito en `spec.md`; no se calcula precio unitario                                                 | PASS      |
| RF-10 | Moneda libre de 1 a 5 caracteres, por defecto `Bs`         | `tests/Feature/ShoppingListsSchemaTest.php`; `tests/Feature/ShoppingListTest.php`; requests                   | PASS      |
| RF-11 | Guarda y muestra la moneda en el encabezado                | `tests/Feature/ShoppingListControllerUpdateTest.php`; `tests/e2e/list-price.spec.js`                          | PASS      |
| RF-12 | Rechaza moneda vacía o mayor de 5 caracteres               | `tests/Feature/ShoppingListControllerUpdateTest.php`                                                          | PASS      |
| RF-13 | Las listas nuevas empiezan con `Bs`                        | `tests/Feature/ShoppingListTest.php`; `tests/Feature/ShoppingListControllerStoreTest.php`                     | PASS      |
| RF-14 | Muestra el símbolo en encabezado y total, no en cada fila  | `tests/e2e/list-price.spec.js`                                                                                | PASS      |
| RF-15 | Totaliza precios de ítems no comprados                     | `tests/e2e/list-price.spec.js`                                                                                | PASS      |
| RF-16 | Muestra total con símbolo cuando corresponde               | `tests/e2e/list-price.spec.js`                                                                                | PASS      |
| RF-17 | Oculta el total si no hay precio pendiente                 | `tests/e2e/list-price.spec.js`                                                                                | PASS      |
| RF-18 | Recalcula tras cambios de precio, estado o ítems           | `tests/e2e/list-price.spec.js`                                                                                | PASS      |
| RF-19 | Conserva el precio visible de ítems comprados              | `tests/e2e/list-price.spec.js`                                                                                | PASS      |
| RF-20 | Oculta el total al borrar el último precio pendiente       | `tests/e2e/list-price.spec.js`                                                                                | PASS      |
| RF-21 | Propaga cambios de precio por polling                      | `tests/Feature/ItemControllerSyncTest.php`; e2e de polling/precios                                            | PASS      |
| RF-22 | Propaga nombre y moneda a dispositivos abiertos            | `tests/e2e/list-polling.spec.js`; `tests/e2e/list-price.spec.js`                                              | PASS      |
| RF-23 | Incluye nombre y moneda en la sincronización               | `tests/Feature/ItemControllerSyncTest.php`; `tests/Feature/ShoppingListResourceTest.php`                      | PASS      |
| RF-24 | Aplica nombre y moneda recibidos en pantalla               | `tests/e2e/list-price.spec.js`; `tests/e2e/list-polling.spec.js`                                              | PASS      |
| RF-25 | Deshacer conserva nombre, cantidad, autor, estado y precio | `tests/e2e/list-price.spec.js`; persistencia de ítems existente                                               | PASS      |
| RF-26 | Permite indicar cantidad al crear                          | `tests/e2e/list.spec.js`                                                                                      | PASS      |
| RF-27 | Permite editar la cantidad y valida el cambio              | `tests/e2e/list.spec.js`; requests y API de Spec 001                                                          | PASS      |
| RF-28 | Muestra cantidad solo cuando existe                        | `tests/e2e/list.spec.js`                                                                                      | PASS      |

## Requisitos no funcionales

| Requisito                 | Evidencia                                                           | Veredicto |
| ------------------------- | ------------------------------------------------------------------- | --------- |
| Formato de importes       | `tests/e2e/list-price.spec.js` verifica formato y separadores       | PASS      |
| Precisión aritmética      | cálculo en centavos en `resources/js/list.js`; suite e2e de totales | PASS      |
| Sincronización en 5 s     | `tests/e2e/list-polling.spec.js`; polling configurado en cliente    | PASS      |
| Cota de escrituras        | `tests/Feature/RateLimitingTest.php`; cambio de moneda usa `writes` | PASS      |
| Mobile-first desde 320 px | viewport Playwright y CSS responsive cubren la capa de cliente      | PASS      |
| Idioma español            | `lang/es/validation.php` y textos visibles; suite Pest              | PASS      |

## Elementos fuera de la automatización

- La verificación manual en dos celulares físicos (precio y total, cambio de
  moneda entre dispositivos y cantidad a 320 px) queda pendiente. No se marca
  como realizada sin evidencia de dispositivos reales.
- No se añadió una convención nueva a `AGENTS.md`; las convenciones descubiertas
  ya estaban documentadas y se respetaron.

## Criterios de finalización

- [x] Todos los RF tienen evidencia automatizada o una decisión explícita de
      alcance.
- [x] `php artisan test`: 148 tests, 747 aserciones, todos en verde.
- [x] `npx playwright test`: 37 tests, todos en verde.
- [x] `npm run build`: compilación correcta.
- [x] `vendor/bin/pint --test`: sin cambios pendientes.
- [x] `git diff --check`: sin errores.
- [ ] Verificación manual en dos celulares físicos.

## Veredicto

**Spec cumplida en automatización; verificación manual pendiente.** La
implementación satisface RF-1–RF-28 y los requisitos no funcionales cubiertos
por código y pruebas. Solo queda ejecutar la comprobación operativa en dos
celulares reales antes de declarar cerrada toda la checklist de la feature.

## Notas

La corrección de `tests/e2e/list.spec.js` acota el selector del editor de nombre
con `data-edit-field`, necesario desde que la fila contiene también el editor de
cantidad. Los cuatro casos de regresión de cantidad verifican alta, edición
parcial, transición de foco y preservación del borrador durante polling.
