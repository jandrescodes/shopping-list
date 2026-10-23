# Spec 003 — Compra semanal: reiniciar, duplicar y exportar como texto

**Estado:** cerrada — validada en `validation.md` (2026-10-23)

## Contexto y objetivo

La compra de la casa se repite casi igual cada semana. Hoy, para repetirla hay que borrar los ítems comprados uno por uno o recrear la lista desde cero, y no hay manera de pasar la lista a una app de mensajes o de notas sin copiarla a mano. Esta feature da tres acciones sobre una lista existente: **reiniciar** las compras (desmarcar de una vez todo lo comprado), **duplicar** la lista como plantilla y **exportarla como texto** plano para pegarla donde se quiera.

No introduce conceptos nuevos de dominio: ni plantillas, ni historial de compras, ni recurrencia programada.

## Usuarios / actores

No aparecen actores nuevos. Quien tiene el enlace de la lista puede reiniciarla, duplicarla y exportarla, igual que ya puede editarla (constitución 4: el `slug` es la única llave de acceso).

## Historias de usuario

- H1: Como quien hace la compra semanal quiero desmarcar de una vez todo lo comprado para repetir la compra sin borrar y recrear ítems.
- H2: Como quien reinicia por error quiero poder deshacerlo al instante.
- H3: Como quien arma la compra semanal quiero duplicar una lista con sus ítems y precios de referencia para no escribirlos otra vez.
- H4: Como miembro de la familia quiero copiar la lista como texto para pegarla en un mensaje o en mis notas.
- H5: Como miembro de la familia quiero que un reinicio hecho en un dispositivo llegue a los demás, para que todos vean la lista desmarcada.

## Requisitos funcionales (criterios de aceptación en EARS)

### Reiniciar compras

- RF-1: CUANDO el usuario pide reiniciar las compras de una lista, EL SISTEMA marca como no comprados todos los ítems de esa lista que estaban comprados, sin alterar su nombre, cantidad ni precio.
- RF-2: EL SISTEMA ejecuta el reinicio de forma inmediata, sin diálogo de confirmación.
- RF-3: CUANDO el reinicio termina, EL SISTEMA muestra un aviso con la acción "Deshacer" durante 5 segundos, el mismo plazo del deshacer de borrado.
- RF-4: CUANDO el usuario pulsa "Deshacer" dentro de ese plazo, EL SISTEMA vuelve a marcar como comprados solo los ítems del reinicio que, según la base de datos al procesar la operación y en una sola transacción, siguen existiendo y siguen desmarcados, sin pisar cambios hechos por otros dispositivos entre tanto (mismo criterio que spec 001 RF-19).
- RF-4a: EL SISTEMA devuelve al reiniciar los ids de los ítems afectados, y el deshacer actúa solo sobre esos ids.
- RF-5: CUANDO vence el plazo del aviso, EL SISTEMA retira la opción "Deshacer".
- RF-6: MIENTRAS la vista actual de la lista no tenga ítems comprados, EL SISTEMA no muestra la acción de reiniciar.
- RF-7: CUANDO una lista se reinicia o se deshace un reinicio, EL SISTEMA sube la versión de la lista una sola vez, sella cada ítem tocado y propaga el cambio como delta por la sincronización existente (spec 001 RF-22 a RF-24), bajo el mismo plazo que cualquier otro cambio de estado de un ítem.
- RF-7a: SI no hay ítems que cambiar, ENTONCES EL SISTEMA no sube la versión de la lista.
- RF-8: CUANDO el reinicio se aplica, EL SISTEMA recalcula el total pendiente de spec 002 (RF-15 y RF-18), que pasa a incluir los precios de los ítems que quedaron sin comprar.

### Duplicar lista

- RF-9: CUANDO el usuario pide duplicar una lista, EL SISTEMA crea una lista nueva con un `slug` nuevo y distinto al de la original.
- RF-10: EL SISTEMA da a la lista nueva el nombre "Copia de <nombre original>", recortado a 60 caracteres (no bytes) y sin espacios finales tras el recorte.
- RF-11: EL SISTEMA copia a la lista nueva, de cada ítem existente de la original y en su orden de creación, su nombre, cantidad y precio, y deja todos los ítems como no comprados.
- RF-12: EL SISTEMA no copia los ítems borrados ni el campo de quién agregó cada ítem.
- RF-13: EL SISTEMA da a la lista nueva la misma moneda que la original.
- RF-14: EL SISTEMA no modifica la lista original al duplicarla.
- RF-15: CUANDO la duplicación termina, EL SISTEMA lleva al usuario a la lista nueva, que se registra en "mis listas" como al abrir cualquier lista (spec 001 RF-6).
- RF-16: EL SISTEMA copia todos los ítems existentes de la original, sin recortarlos: el tope de 200 ítems (spec 001 RF-20) solo se evalúa en el alta de ítems, y una lista puede tener más de 200 activos.
- RF-17: EL SISTEMA aplica a la duplicación el mismo límite de creación de listas por IP (`lists-create`) que a la creación normal.
- RF-18: SI se excede ese límite, ENTONCES EL SISTEMA rechaza la duplicación y muestra el aviso de espera de 429 de creación de listas de `home.js` ("Creaste demasiadas listas seguidas…"), con el tiempo restante en minutos según `Retry-After`.
- RF-19: SI la lista origen no existe (borrada o `slug` inexistente), ENTONCES EL SISTEMA responde igual que ante cualquier otra lista inexistente (404 uniforme), sin distinguir los casos.
- RF-20: EL SISTEMA hace la duplicación de forma atómica: o se crea la lista con todos sus ítems, o no se crea nada.

### Exportar como texto

- RF-21: CUANDO el usuario pide exportar la lista, EL SISTEMA copia al portapapeles un texto plano con el nombre de la lista y un ítem por línea, y muestra un aviso de confirmación ("Lista copiada").
- RF-22: EL SISTEMA escribe cada ítem como `- [ ] <nombre> (<cantidad>) — <precio>` si está pendiente y `- [x] …` si está comprado; omite la cantidad y el precio cuando el ítem no los tiene, sin marcadores vacíos. El precio va solo como número, sin símbolo de moneda (spec 002 RF-8 y RF-14).
- RF-22a: EL SISTEMA ordena los ítems como la interfaz: pendientes primero y comprados después, cada grupo en orden de creación (spec 001 RF-18).
- RF-23: DONDE la lista tiene total pendiente (spec 002 RF-15 a RF-17), EL SISTEMA lo añade como última línea, `Total pendiente: <símbolo> <importe>`; en el total sí va el símbolo de la moneda.
- RF-24: EL SISTEMA formatea los importes del texto con el mismo formateador que la interfaz, de modo que ambos coinciden siempre (spec 002, RNF "Formato de importes").

Ejemplo de texto exportado:

```
Compra semanal
- [ ] Leche (2) — 12,50
- [ ] Huevos
- [x] Pan — 3,00
Total pendiente: Bs 12,50
```
- RF-25: EL SISTEMA no incluye en el texto los ítems borrados, el `slug` ni el enlace de la lista.
- RF-26: SI el navegador no permite escribir en el portapapeles, ENTONCES EL SISTEMA muestra el texto en un campo seleccionable para copiarlo a mano.

## Requisitos no funcionales

- **Sincronización**: el reinicio y su deshacer se propagan con el intervalo de 3–4 s y el plazo de 5 s de spec 001 (RF-22 a RF-24); la sincronización sigue siendo solo lectura.
- **Cotas de peticiones** (RNF "Límite de peticiones" de spec 001): reiniciar es una sola escritura y deshacer es otra, con todos los ids en una petición, de modo que una lista de 200 ítems no agota las 120/min por IP; duplicar consume `lists-create` (10/h por IP); exportar no hace peticiones al servidor.
- **Avisos nuevos**: cada aviso o campo nuevo lleva `id` o clase semántica propia y no reutiliza `bg-green-50`, `bg-gray-100`, `bg-amber-50` ni `bg-blue-50`, reservadas para los tests e2e (`AGENTS.md`).
- **Atomicidad**: reiniciar y duplicar no dejan estados a medias ante un fallo.
- **Plataformas y accesibilidad**: hereda spec 001 y las reglas de UI del proyecto (mobile-first, targets de 44 px, contraste, foco de teclado); las acciones viven en el menú de opciones.
- **Idioma**: interfaz y avisos en español; código en inglés.

## Casos límite

- **Reiniciar sin ítems comprados**: la acción no se muestra (RF-6); una petición directa responde sin cambios y sin subir la versión (RF-7a).
- **Reiniciar dos veces seguidas**: la segunda no cambia nada.
- **Deshacer tras un cambio ajeno**: respeta RF-4; un ítem borrado o vuelto a marcar por otro dispositivo no se toca.
- **Duplicar una lista vacía**: crea una lista nueva sin ítems, con nombre y moneda.
- **Nombre de 60 caracteres**: "Copia de" se recorta a 60 en total; la copia no falla por validación.
- **Duplicar una lista de 200 ítems**: se copian los 200.
- **Doble envío de la duplicación**: crea dos listas distintas, cada una consume cupo de `lists-create`.
- **Exportar con comillas, emojis o saltos en nombres**: el texto sale tal cual, un ítem por línea (los saltos de línea del nombre se reemplazan por espacio).
- **Exportar una lista vacía**: copia solo el nombre; no se ofrece total.
- **Dos avisos de deshacer a la vez** (borrar un ítem durante la ventana del reinicio): el último reemplaza al anterior, que deja de poder deshacerse.
- **Sin red**: reiniciar y duplicar fallan con el aviso de desconexión existente (spec 001 RF-26, sin cola de escrituras); exportar funciona sin red.
- **Lista borrada desde otro dispositivo**: reiniciar responde con el 404 uniforme (spec 001 RF-27).
- **Recargar la página** durante la ventana de deshacer: el deshacer se pierde (es estado del cliente).
- **Duplicar un `slug` inexistente**: responde 404 uniforme y consume cupo de `lists-create`, porque el límite se evalúa antes de resolver la lista.

## Fuera de alcance

- Elegir qué ítems se copian al duplicar.
- Exportar a `.txt`, CSV o PDF, y compartir por la hoja nativa (Web Share) o QR: siguen en el backlog.
- Importar texto a una lista.
- Historial de compras, reinicio programado o recurrente.
- Cambiar la moneda o los precios al duplicar.

## Criterios de finalización

- [ ] RF-1 a RF-26 (incluidos RF-4a, RF-7a y RF-22a) verificados con tests Pest (API y persistencia) y Playwright (capa de cliente).
- [ ] El reinicio y su deshacer funcionan entre dos dispositivos sin pisar cambios ajenos.
- [ ] La duplicación respeta el límite `lists-create` y es atómica.
- [ ] El texto exportado coincide con la interfaz en importes y total.
- [ ] `php artisan test`, `npx playwright test` y `vendor/bin/pint` en verde; axe sin violaciones a 375 y 1280 px.
- [ ] `docs/roadmap.md` y `docs/app-flow.md` actualizados y `validation.md` generado en `/sdd:validate`.

## Dudas abiertas

Ninguna. La ubicación de las tres acciones en el menú de opciones se resolvió en el plan (D9).

## Clarificaciones

### Fase 2 — spec inicial

- P1 (qué se copia al duplicar): nombre, cantidad y precio; moneda heredada. Los precios sirven como referencia de plantilla y se corrigen fácil.
- P2 (nombre y navegación): "Copia de <nombre>" y navegar a la lista nueva; sin diálogo.
- P3 (reiniciar): inmediato, con aviso "Deshacer".
- P4 (exportar): copiar al portapapeles texto plano con estados, precios y total; hoja nativa y `.txt` quedan fuera.
- P5 (bordes): mismo aviso 429 en duplicar; se copian hasta los 200 ítems; reiniciar sin comprados no se ofrece.
- P6 (concurrencia y alcance): el deshacer solo restituye ítems que siguen existiendo y desmarcados; lista de fuera de alcance confirmada.

### Fase 2 — revisión de consistencia

- El precio por ítem va sin símbolo y el símbolo solo en el total, para respetar spec 002 RF-14; esto amplía el fuera de alcance de spec 002 ("exportación de totales"), que esta spec asume a propósito.
- Reiniciar no pide confirmación (a diferencia de limpiar comprados, spec 001 RF-19) porque tiene deshacer de 5 s.
- Al duplicar se copian todos los ítems existentes, aun si superan 200.
