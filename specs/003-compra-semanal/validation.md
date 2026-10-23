# Validación — Spec 003

**Date:** 2026-10-23

## Verificación ejecutada

| Comando                                                                               | Resultado                                                                                                                   |
| ------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| `php artisan test`                                                                    | 212 pasadas (1113 assertions)                                                                                               |
| `npx playwright test`                                                                 | 58 pasadas                                                                                                                  |
| `vendor/bin/pint --test`                                                              | limpio                                                                                                                      |
| axe-core 4.14.0 (`wcag2a`, `wcag2aa`, `wcag21aa`) a 375 y 1280 px, fuera del proyecto | 0 violaciones con el menú abierto, con `#reset-undo` y `#export-notice` visibles a la vez, y con `#export-fallback` visible |

## Recorrido RF por RF

Abreviaturas: **RST** = `ItemControllerResetPurchasedTest`, **RSV** = `ItemControllerRestorePurchasedTest`, **DUP** = `ShoppingListDuplicateTest`, **DCT** = `ShoppingListControllerDuplicateTest`, **RL** = `RateLimitingTest`, **W** = `tests/e2e/list-weekly.spec.js`.

| RF     | Resumen                                                                               | Evidencia                                                                                                                                         | Veredicto                                     |
| ------ | ------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------- |
| RF-1   | Reiniciar desmarca lo comprado sin tocar nombre, cantidad ni precio                   | RST (desmarca solo comprados; conserva datos); W "reiniciar desmarca todo, conserva los datos"                                                    | PASS                                          |
| RF-2   | El reinicio es inmediato, sin diálogo                                                 | W "reiniciar desmarca todo…" (un clic en el menú basta)                                                                                           | PASS                                          |
| RF-3   | Aviso con "Deshacer" durante 5 s                                                      | W "reiniciar desmarca todo…" (`#reset-undo`); W "deshacer reinicio: el aviso vence a los 5 s"                                                     | PASS                                          |
| RF-4   | Deshacer restaura solo lo que sigue existiendo y desmarcado                           | RSV (omite borrados, ya comprados, de otra lista); W "deshacer reinicio restaura solo lo que sigue desmarcado"                                    | PASS                                          |
| RF-4a  | El reinicio devuelve los ids y el deshacer actúa solo sobre ellos                     | RST (`reset_ids`); RSV; RestorePurchasedRequestTest; W "deshacer reinicio restaura…"                                                              | PASS                                          |
| RF-5   | Al vencer el plazo se retira "Deshacer"                                               | W "deshacer reinicio: el aviso vence a los 5 s"                                                                                                   | PASS                                          |
| RF-6   | Sin comprados no se muestra la acción                                                 | W "reiniciar no se ofrece mientras no haya ítems comprados"                                                                                       | PASS                                          |
| RF-7   | Una sola subida de versión, ítems sellados, propagado por delta                       | RST (versión +1 y sellado; delta de sync); RSV (versión +1 y sellado); W "otro dispositivo ve el reinicio por el polling"                         | PASS                                          |
| RF-7a  | Sin ítems que cambiar no sube la versión                                              | RST (sin comprados; segundo reinicio seguido); RSV (nada aplicable)                                                                               | PASS                                          |
| RF-8   | El total pendiente se recalcula al reiniciar                                          | W "reiniciar desmarca todo…" (`Bs 1,00` → `Bs 16,00`)                                                                                             | PASS                                          |
| RF-9   | La copia tiene un slug nuevo y distinto                                               | DUP "creates a new list with a different slug"; W "duplicar abre la copia…"                                                                       | PASS                                          |
| RF-10  | Nombre "Copia de <nombre>", recortado a 60 caracteres sin espacios finales            | DUP (nombre, recorte por caracteres, espacios finales); W "duplicar recorta el nombre a 60 caracteres"                                            | PASS                                          |
| RF-11  | Copia nombre, cantidad y precio, sin marcar y en orden de creación                    | DUP (copia sin marcar; precio cero; orden de creación); W "duplicar abre la copia…"                                                               | PASS                                          |
| RF-12  | No copia ítems borrados ni quién los agregó                                           | DUP "does not copy deleted items"; DUP "copies name, quantity and price … without added_by"                                                       | PASS                                          |
| RF-13  | Misma moneda que la original                                                          | DUP "inherits the currency"; W "duplicar abre la copia…" (USD)                                                                                    | PASS                                          |
| RF-14  | La original no se modifica                                                            | DUP "leaves the original list and its items untouched"; DCT; W "duplicar abre la copia…"                                                          | PASS                                          |
| RF-15  | Tras duplicar se navega a la copia y queda en "mis listas"                            | W "duplicar abre la copia…"; W "duplicar deja la copia en mis listas"                                                                             | PASS                                          |
| RF-16  | Se copian todos los ítems aunque pasen de 200                                         | DUP "copies every item even when the original has more than 200"                                                                                  | PASS                                          |
| RF-17  | Duplicar consume el cupo `lists-create`                                               | RL (cupo compartido con crear; 429 al exceder 10/h; no gasta `writes`)                                                                            | PASS                                          |
| RF-18  | 429 de duplicar muestra la espera en minutos                                          | RL ("answers 429 with Retry-After"); W "duplicar con 429 muestra la espera en minutos"; `list-throttle.spec.js` (home)                            | PASS                                          |
| RF-19  | Slug inexistente responde el 404 uniforme                                             | DCT "answers the uniform 404 for a slug that does not exist"; RST y RSV (404 uniforme); RL (gasta cupo)                                           | PASS                                          |
| RF-20  | La duplicación es atómica                                                             | DUP "creates nothing when copying an item fails midway"                                                                                           | PASS                                          |
| RF-21  | Copiar como texto al portapapeles con aviso "Lista copiada"                           | W "copia el texto exacto con pendientes antes que comprados y avisa"                                                                              | PASS                                          |
| RF-22  | Formato `- [ ] nombre (cantidad) — precio`, sin marcadores vacíos, precio sin símbolo | W "copia el texto exacto…"; W "un ítem sin cantidad ni precio no deja marcadores vacíos"                                                          | PASS                                          |
| RF-22a | Pendientes primero y comprados después                                                | W "copia el texto exacto…" (Pan comprado va al final)                                                                                             | PASS                                          |
| RF-23  | Última línea `Total pendiente: <moneda> <importe>` solo si hay total                  | W "copia el texto exacto…"; W "una lista vacía copia solo el nombre"                                                                              | PASS                                          |
| RF-24  | Importes con el mismo formateador que la interfaz                                     | W "el importe del texto coincide con el total de la interfaz (1234,50)"                                                                           | PASS                                          |
| RF-25  | El texto no incluye ítems borrados, slug ni enlace | W "un ítem borrado no aparece en el texto"; W "copia el texto exacto…" (texto completo, sin slug ni enlace) | PASS |
| RF-26  | Sin permiso de portapapeles, campo seleccionable                                      | W "sin permiso de portapapeles muestra el texto seleccionado para copiar a mano"                                                                  | PASS                                          |

## Requisitos no funcionales

| Requisito                   | Evidencia                                                                                                                                                                                                  | Veredicto |
| --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------- |
| Sincronización              | RST (delta por sync); W "otro dispositivo ve el reinicio por el polling"; la sync sigue de solo lectura                                                                                                    | PASS      |
| Cotas de peticiones         | RL (reiniciar y restaurar comparten `writes`; duplicar gasta `lists-create`); reinicio y deshacer son una petición cada uno (RST, RSV, 1000 ids en RestorePurchasedRequestTest); exportar es 100 % cliente | PASS      |
| Avisos nuevos               | `#reset-undo` (`bg-sky-50`), `#export-notice` (`bg-teal-50`), `#export-fallback`; los 58 e2e, incluidos los de las clases reservadas, pasan                                                                | PASS      |
| Atomicidad                  | DUP (revierte si falla a mitad); RST y RSV usan `ListVersion::write` con bloqueo                                                                                                                           | PASS      |
| Plataformas y accesibilidad | axe 0 violaciones a 375 y 1280 px; W "los botones nuevos del menú miden al menos 44 px"; W "el menú se recorre con teclado…"                                                                               | PASS      |
| Idioma                      | Interfaz y avisos en español, revisados en las vistas y en los tests e2e                                                                                                                                   | PASS      |

## Casos límite

Cubiertos: reiniciar sin comprados y dos veces seguidas (RST); deshacer tras cambio ajeno (RSV, W); duplicar lista vacía, de 200+ ítems y nombre de 60 caracteres (DUP, W); doble envío (DCT); exportar lista vacía (W); dos avisos de deshacer (W "borrar un ítem durante la ventana…"); sin red al duplicar (W); lista inexistente (RST, RSV, DCT); slug inexistente gasta cupo (RL).

No cubiertos por un test: exportar con comillas, emojis o saltos en nombres (los saltos de línea se probaron a mano en T9 con "Pan\nintegral", pero no hay test permanente); reiniciar sin red; exportar sin red; recargar durante la ventana de deshacer (es estado del cliente, sin lógica propia).

## Uncovered FRs

Ninguno. La brecha de RF-25 (ítems borrados) se cerró con el test "un ítem borrado no aparece en el texto" y se repitió el recorrido.

## Criterios de finalización

- [x] RF-1 a RF-26 verificados con tests Pest y Playwright.
- [x] El reinicio y su deshacer funcionan entre dos dispositivos sin pisar cambios ajenos (W "otro dispositivo ve el reinicio…" y "deshacer reinicio restaura solo lo que sigue desmarcado").
- [x] La duplicación respeta el límite `lists-create` y es atómica (RL, DUP).
- [x] El texto exportado coincide con la interfaz en importes y total (W, caso 1234,50).
- [x] `php artisan test`, `npx playwright test` y `vendor/bin/pint` en verde; axe sin violaciones a 375 y 1280 px.
- [x] `docs/roadmap.md` y `docs/app-flow.md` actualizados y `validation.md` generado.

## Verdict

**Spec cumplida.** Los 26 RF (incluidos RF-4a, RF-7a y RF-22a) tienen evidencia automática en verde: 212 Pest, 58 Playwright, Pint limpio y axe sin violaciones a 375 y 1280 px. La primera pasada dejó una brecha en RF-25 (ítems borrados); se cerró con un test e2e y se repitió el recorrido. La verificación manual se hizo con dos pestañas a 390 px en el navegador del MCP de Playwright (reinicio visto en 3,4 s, deshacer con cambio ajeno, duplicar y copiar como texto), no en celulares reales.

## Notes

- Verificación manual hecha con navegador emulado (dos pestañas a 390 px) y con dos contextos Pixel 7 de Playwright; no se probó en celulares físicos ni el pegado en una app de mensajes.
- axe-core se ejecutó fuera del proyecto, según `docs/ui-guidelines.md`; no queda un spec de axe permanente en `tests/e2e`.

## Updated files

- `docs/roadmap.md`: la feature 003 pasa de "Siguiente 🔜" a "Hecho ✅" (el archivo usa esos encabezados en lugar de `## Done` / `## Next`).
- `docs/schema.md`: sin cambios (no hay columnas nuevas).
