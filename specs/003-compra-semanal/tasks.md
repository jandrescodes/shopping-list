# Tareas — Spec 003

Tareas de <30 min, ordenadas por dependencia. Cada una: sus RF, un checkbox y
una línea "Hecho cuando:" verificable. Derivadas de `plan.md`; los RF salen de
`spec.md`.

Puerta de verificación por tarea (`AGENTS.md`): `php artisan test` siempre;
`npx playwright test` cuando la tarea toque `resources/js/`; `vendor/bin/pint`
sobre lo tocado. Ninguna tarea se marca hecha en rojo.

Reglas que atraviesan todo el bloque:

- **`ApiRoutesTest` se actualiza en la misma tarea que añade cada ruta** (T1, T4
  y T6), porque afirma el número exacto de rutas del contrato y la puerta exige
  verde. Se conserva la exactitud de la aserción, nunca se relaja.
- **Escritura de tests primero**: en las tareas de backend y de cliente el test
  se escribe antes y se ve fallar por el motivo correcto antes de implementar.
- **Clases de fondo reservadas**: ningún elemento nuevo usa `bg-blue-50`,
  `bg-green-50`, `bg-gray-100` ni `bg-amber-50`; cada aviso lleva su `id`.

## Backend

- [x] T0. Baseline: ejecutar `php artisan test` y `npx playwright test` sin
      cambios y anotar los conteos de partida. (Estado de partida del plan)
      Hecho cuando: ambas suites pasan en verde y los conteos (esperados 158 Pest
      y 39 Playwright) quedan escritos en el mensaje de la tarea.

- [x] T1. `POST /lists/{list}/items/reset-purchased`: test
      `ItemControllerResetPurchasedTest` (desmarca solo comprados activos,
      devuelve `reset_ids`, no cambia nombre/cantidad/precio, una sola subida de
      versión, ítems sellados con esa versión), ruta bajo `throttle:writes`,
      `ItemController::resetPurchased` y `ShoppingList::resetPurchasedItems()`
      vía `ListVersion::write`; `ApiRoutesTest` pasa a diez rutas. (RF-1, RF-4a,
      RF-7)
      Hecho cuando: `php artisan test --filter=ItemControllerResetPurchasedTest`
      y `--filter=ApiRoutesTest` pasan, y el test falló antes de implementar.

- [x] T2. Casos límite del reinicio: sin comprados no sube la versión y devuelve
      `reset_ids: []`, doble reinicio, ítems borrados no entran, lista inexistente
      responde 404 uniforme, el delta de sync (`cursor` previo) trae los ítems
      reiniciados. (RF-7, RF-7a, RF-19)
      Hecho cuando: `php artisan test --filter=ItemControllerResetPurchasedTest`
      pasa con esos casos y la versión de la lista no cambia en el caso vacío.

- [x] T3. `RestorePurchasedRequest`: `ids` requerido, array de 1 a 1000 enteros
      ≥ 1 sin repetidos; test `RestorePurchasedRequestTest` con los casos
      válidos e inválidos (vacío, no entero, 0, repetidos, 1001). (RF-4)
      Hecho cuando: `php artisan test --filter=RestorePurchasedRequestTest`
      pasa y los mensajes de error están en `lang/es/validation.php`.

- [x] T4. `POST /lists/{list}/items/restore-purchased`: test
      `ItemControllerRestorePurchasedTest` (restaura solo ítems de esa lista,
      activos y desmarcados; ignora ids ajenos, inexistentes, borrados o ya
      comprados; sin coincidencias no sube la versión; una sola subida de versión
      con coincidencias; 422 con `ids` inválidos), ruta bajo `throttle:writes`,
      `ItemController::restorePurchased` y `ShoppingList::restorePurchasedItems()`;
      `ApiRoutesTest` pasa a once rutas. (RF-4, RF-4a, RF-7, RF-7a)
      Hecho cuando: `php artisan test --filter=ItemControllerRestorePurchasedTest`
      y `--filter=ApiRoutesTest` pasan.

- [x] T5. `ShoppingList::duplicate()`: test `ShoppingListDuplicateTest` (slug
      distinto, nombre "Copia de <nombre>" recortado a 60 caracteres multibyte y
      sin espacios finales, moneda heredada, ítems con nombre/cantidad/precio
      sin marcar y sin `added_by`, orden de creación, sin borrados, lista vacía,
      más de 200 ítems copiados, original intacta, atomicidad: un fallo a mitad
      no deja lista ni ítems). (RF-9 a RF-14, RF-16, RF-20)
      Hecho cuando: `php artisan test --filter=ShoppingListDuplicateTest` pasa.

- [x] T6. `POST /lists/{list}/duplicate`: test
      `ShoppingListControllerDuplicateTest` (`201` con `slug`, `name`, `url` y
      `Location`, lista original intacta, 404 uniforme para `slug` inexistente),
      ruta bajo `throttle:lists-create` y `ShoppingListController::duplicate`;
      `ApiRoutesTest` pasa a doce rutas. (RF-9, RF-19)
      Hecho cuando: `php artisan test --filter=ShoppingListControllerDuplicateTest`
      y `--filter=ApiRoutesTest` pasan.

- [x] T7. Cotas: en `RateLimitingTest`, duplicar consume `lists-create` (11.ª
      petición en la hora responde 429 con `Retry-After`, y un `slug` inexistente
      también gasta cupo) y reiniciar/restaurar consumen `writes`. (RF-17,
      RF-18)
      Hecho cuando: `php artisan test --filter=RateLimitingTest` pasa con esos
      casos.

## Cliente

- [x] T8. Helper `resources/js/throttle.js` con `throttledListsMessage(retryAfter)`
      (texto en minutos); `home.js` lo usa sin cambio de comportamiento.
      (RF-18)
      Hecho cuando: `npx playwright test list-throttle` y el spec de la home
      pasan sin cambios en sus aserciones, y `home.js` ya no contiene el texto
      "Creaste demasiadas listas" (`grep` sin resultados).

- [x] T9. Exportar como texto: `exportText()` y `copyAsText()` en `list.js`,
      botón "Copiar como texto" en `#list-menu`, aviso `#export-notice`
      (`bg-teal-50`) y campo `#export-fallback` readonly seleccionado; formato
      de RF-22 a RF-24 con `AMOUNT_FORMAT`, sin símbolo por ítem, total con
      moneda, saltos de línea del nombre reemplazados por espacio. (RF-21 a
      RF-26, RF-22a)
      Hecho cuando: `npx playwright test list-weekly -g "texto"` pasa (T10) y
      `php artisan test` sigue verde.

- [x] T10. E2E de exportar en `tests/e2e/list-weekly.spec.js`: texto exacto con
      permiso de portapapeles concedido (ejemplo del RF-24 de la spec), pendientes
      antes que comprados, ítems sin precio sin marcador, lista vacía solo con el
      nombre, respaldo seleccionable sin permiso, y el importe del texto coincide
      con el total de la interfaz con un precio de 1234,50. (RF-21 a RF-26)
      Hecho cuando: `npx playwright test list-weekly -g "texto"` pasa y el test
      falló antes de T9.

- [x] T11. Reiniciar en el cliente: `resetPurchased()`, botón "Reiniciar compras"
      con `x-show="hasPurchased"`, aviso `#reset-undo` (`bg-sky-50`, 5 s) y
      ranura de deshacer compartida: el último aviso reemplaza al anterior
      (`offerUndo` y el reinicio se limpian mutuamente). Recalcula el total.
      (RF-2, RF-3, RF-5, RF-6, RF-8)
      Hecho cuando: `npx playwright test list-weekly -g "reiniciar"` pasa (T13)
      y `list.spec.js` (deshacer de borrado) sigue verde.

- [x] T12. Deshacer reinicio: `undoReset()` envía los `reset_ids` a
      `restore-purchased`, aplica `restored_ids` y limpia el aviso; el aviso
      desaparece a los 5 s. (RF-4, RF-4a, RF-5)
      Hecho cuando: `npx playwright test list-weekly -g "deshacer reinicio"` pasa
      (T13).

- [x] T13. E2E de reiniciar y deshacer: desmarca todo, mantiene nombre/cantidad/
      precio, total recalculado, sin comprados no aparece el botón, deshacer
      restaura solo lo que sigue desmarcado (un ítem borrado y otro marcado por
      la API entre tanto quedan intactos), el aviso vence a los 5 s, borrar un
      ítem durante la ventana reemplaza el aviso, y otro dispositivo ve el
      reinicio por el polling. (RF-1 a RF-8)
      Hecho cuando: `npx playwright test list-weekly -g "reiniciar|deshacer reinicio"`
      pasa y los tests fallaron antes de T11 y T12.

- [x] T14. Duplicar en el cliente: `duplicateList()` con botón "Duplicar lista" en
      `#list-menu`; en éxito navega a `/l/{slug}`; en 429 muestra
      `throttledListsMessage` y en error de red el aviso de desconexión.
      (RF-15, RF-18)
      Hecho cuando: `npx playwright test list-weekly -g "duplicar"` pasa (T15).

- [x] T15. E2E de duplicar: navega a la lista nueva con ítems sin marcar, precios
      y moneda heredada, la original queda intacta, aparece en "mis listas", el
      nombre se recorta a 60, 429 muestra el texto en minutos y sin red muestra
      el aviso de desconexión. (RF-9 a RF-18)
      Hecho cuando: `npx playwright test list-weekly -g "duplicar"` pasa y falló
      antes de T14.

- [x] T16. Accesibilidad y UI: axe-core a 375 y 1280 px sin violaciones con los
      avisos `#reset-undo` y `#export-notice` visibles, targets de 44 px
      (`min-h-11`) en los tres botones del menú y recorrido por teclado del menú
      (abrir, navegar, Escape, foco al botón). (RNF de plataformas)
      Hecho cuando: el spec de axe de `tests/e2e` incluye esos casos y pasa;
      `npx playwright test` completo en verde.

- [x] T17. Service worker: subir `CACHE_VERSION` de `sw.js` a `v4` (la home, en
      `STATIC_SHELL` y cache-first, usa `home.js`/`throttle.js` recompilados) y
      ajustar `ServiceWorkerTest`. `/l/{slug}` no se cachea, así que la vista de
      lista no obliga al bump por sí sola.
      Hecho cuando: `php artisan test --filter=ServiceWorkerTest` pasa
      afirmando `v4`.

## Convenciones

- [x] T18. Anotar en `AGENTS.md` las convenciones nuevas: las tres rutas, la
      ranura única de deshacer (el último aviso reemplaza al anterior), los
      fondos reservados nuevos (`bg-sky-50` reinicio, `bg-teal-50` copiado) y
      `throttle.js` compartido; verificar de paso que la sección de archivos
      clave nombra `throttle.js`. Ejecutar `vendor/bin/pint` y la suite completa.
      Hecho cuando: `grep -n "reset-purchased\|bg-sky-50\|bg-teal-50\|throttle.js" AGENTS.md`
      devuelve las cuatro menciones, `vendor/bin/pint --test` sale limpio y
      `php artisan test` y `npx playwright test` pasan.

## Cierre de la feature

_Checkboxes sueltos, fuera de la numeración `Tn` — no se implementan con
`/sdd:implement`._

- [x] Suite completa verde: `php artisan test` y `npx playwright test` sin
      fallos, y `vendor/bin/pint --test` sin cambios pendientes.
- [x] Verificación de todos los RF: `/sdd:validate` →
      `specs/003-compra-semanal/validation.md` (recorrido RF por RF + veredicto).
- [x] `docs/roadmap.md`: la feature 003 anotada como en curso con su rango `Tn`
      mientras avanza, y movida a "Hecho ✅" por `/sdd:validate`; quitar del
      backlog "Duplicar lista" y "Reiniciar compras".
- [x] `docs/app-flow.md` regenerado con `/sdd:docs flow 003-compra-semanal`
      (reiniciar, deshacer, duplicar, exportar, estados de error y de vacío);
      `docs/schema.md` verificado sin cambios (no hay columnas nuevas).
- [x] CHANGELOG: entrada `[1.3.0]` de la feature al cerrarla.
- [x] README: revisar si lista las funciones de la app y añadir las tres.
- [x] Verificación manual en dos celulares: reiniciar en uno y verlo en el otro
      en ≤ 5 s, deshacer con un cambio ajeno entre tanto, duplicar una lista y
      pegar el texto exportado en un mensaje.
- [x] Release `1.3.0`: tag `1.3.0` sin prefijo `v` tras el CI verde y release
      publicado, que dispara el deploy a Hostinger (`docs/deploy.md`).
- [x] Convenciones descubiertas durante la implementación anotadas en
      `AGENTS.md` sobre la marcha, no en el cierre.

## Cobertura RF → tarea

| RF     | Tareas           |
| ------ | ---------------- |
| RF-1   | T1, T13          |
| RF-2   | T11, T13         |
| RF-3   | T11, T13         |
| RF-4   | T3, T4, T12, T13 |
| RF-4a  | T1, T4, T12      |
| RF-5   | T11, T12, T13    |
| RF-6   | T11, T13         |
| RF-7   | T1, T2, T4       |
| RF-7a  | T2, T4           |
| RF-8   | T11, T13         |
| RF-9   | T5, T6, T15      |
| RF-10  | T5, T15          |
| RF-11  | T5, T15          |
| RF-12  | T5               |
| RF-13  | T5, T15          |
| RF-14  | T5, T6, T15      |
| RF-15  | T14, T15         |
| RF-16  | T5               |
| RF-17  | T7               |
| RF-18  | T7, T8, T14, T15 |
| RF-19  | T2, T6           |
| RF-20  | T5               |
| RF-21  | T9, T10          |
| RF-22  | T9, T10          |
| RF-22a | T9, T10          |
| RF-23  | T9, T10          |
| RF-24  | T9, T10          |
| RF-25  | T9, T10          |
| RF-26  | T9, T10          |
