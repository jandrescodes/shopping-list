# Spec 004 — Armar y planear la lista: reordenar, autocompletar y presupuesto

**Estado:** borrador — pendiente de aprobación

## Contexto y objetivo

Armar la compra tiene hoy tres fricciones: los ítems salen en orden de creación (no se pueden agrupar por pasillo), cada
ítem se escribe completo aunque se compre todas las semanas, y no hay forma de saber si la compra se pasa de lo que se
quiere gastar. Esta feature da tres mejoras sobre la lista existente: **reordenar** ítems a mano, **autocompletar**
nombres desde lo que la propia lista ya tuvo y fijar un **presupuesto** opcional con aviso al superarlo.

No introduce conceptos nuevos de dominio: ni categorías, ni catálogo global de productos, ni historial de compras
(constitución 7).

## Usuarios / actores

No aparecen actores nuevos. Quien tiene el enlace de la lista puede reordenarla, usar el autocompletado y fijar el
presupuesto, igual que ya puede editarla (constitución 4).

## Historias de usuario

- H1: Como quien hace la compra quiero ordenar los ítems como recorro el supermercado para no ir y volver entre
  pasillos.
- H2: Como miembro de la familia quiero que el orden que fijé llegue a los demás dispositivos.
- H3: Como quien agrega ítems todas las semanas quiero que la app me sugiera nombres que ya usé para no escribirlos
  completos.
- H4: Como quien cuida el gasto quiero fijar un tope a la lista y que me avise si el total pendiente lo supera.
- H5: Como quien duplica la compra semanal quiero que la copia conserve el orden y el presupuesto.

## Requisitos funcionales (criterios de aceptación en EARS)

### Reordenar ítems

- RF-1: CUANDO el usuario mueve un ítem pendiente a otra posición entre los pendientes de su lista, EL SISTEMA guarda
  ese orden y lo muestra al recargar la lista.
- RF-2: EL SISTEMA mantiene la separación de spec 001 RF-18 (pendientes primero, comprados después): el orden manual
  solo se edita entre pendientes, y no se puede mover un ítem de un grupo al otro arrastrándolo.
- RF-2a: EL SISTEMA guarda una única posición por ítem para toda la lista; los comprados se muestran en el orden de esa
  misma posición, sin controles para moverlos.
- RF-3: CUANDO el usuario marca un ítem como comprado, EL SISTEMA lo muestra en el grupo de comprados conservando su
  posición; CUANDO lo desmarca, vuelve al grupo de pendientes en esa posición.
- RF-4: CUANDO se agrega un ítem nuevo, EL SISTEMA lo coloca al final del grupo de pendientes.
- RF-4a: CUANDO el usuario deshace el borrado de un ítem (spec 002 RF-25), EL SISTEMA lo restituye en la posición que
  tenía al borrarlo, relativa a los demás ítems que siguen existiendo.
- RF-5: EL SISTEMA ofrece dos maneras de reordenar: arrastrar con un asa dedicada que no interfiere con el scroll
  táctil, y botones "subir" y "bajar" por ítem como alternativa accesible por teclado y lector de pantalla.
- RF-6: CUANDO se reordena una lista, EL SISTEMA sube la versión de la lista una sola vez por operación y propaga el
  nuevo orden por la sincronización existente (spec 001 RF-22 a RF-24), bajo el mismo plazo que cualquier otro cambio.
  La posición de cada ítem viaja en la carga completa y en el delta; esto corrige spec 001 RF-18 ("por fecha de
  creación", delta sin posición) y spec 003 RF-11 y RF-22a.
- RF-7: SI dos dispositivos reordenan la misma lista casi a la vez, ENTONCES EL SISTEMA aplica el último que llega al
  servidor y el otro dispositivo ve el resultado en su siguiente sincronización, sin error ni aviso.
- RF-8: SI el ítem movido fue borrado, comprado o cambió de grupo en otro dispositivo antes de llegar la operación,
  ENTONCES EL SISTEMA ignora el movimiento sin error y el cliente adopta el estado del servidor en la siguiente
  sincronización.
- RF-8a: SI otro dispositivo agregó ítems que el dispositivo que reordena aún no conoce, ENTONCES EL SISTEMA los
  conserva sin perderlos y sin alterar su posición relativa entre sí.
- RF-9: EL SISTEMA da a los ítems existentes antes de esta feature un orden inicial igual al que ya se les muestra
  (fecha de creación y, a igualdad, id), de modo que la lista se ve igual tras el despliegue.
- RF-10: SI la lista tiene menos de dos ítems pendientes, ENTONCES EL SISTEMA no muestra controles de reordenar.

### Autocompletar ítems frecuentes

- RF-11: CUANDO el usuario escribe al menos 2 caracteres en el campo de nombre al agregar un ítem, EL SISTEMA sugiere
  hasta 5 nombres de ítems de esa misma lista que contengan lo escrito, sin distinguir mayúsculas ni tildes.
- RF-12: EL SISTEMA toma las sugerencias de los ítems activos de la lista (pendientes y comprados), sin repetir nombres:
  dos nombres que solo difieren en mayúsculas o tildes cuentan como el mismo y se sugiere el del ítem más reciente. Los
  ítems borrados no aportan sugerencias.
- RF-13: EL SISTEMA no usa ítems de otras listas ni un catálogo global como fuente de sugerencias.
- RF-14: CUANDO el usuario elige una sugerencia, EL SISTEMA completa solo el nombre en el campo; no copia cantidad,
  precio ni estado del ítem original.
- RF-15: EL SISTEMA permite ignorar las sugerencias y agregar el texto escrito tal cual.
- RF-16: MIENTRAS el nombre escrito coincide por completo con una sugerencia (sin distinguir mayúsculas ni tildes), EL
  SISTEMA no la repite como sugerencia.
- RF-17: EL SISTEMA no guarda historial propio para el autocompletado: lo calcula de los ítems que la lista ya tiene
  (constitución 7).
- RF-18: EL SISTEMA permite elegir sugerencias con teclado y con toque, con targets táctiles de 44 px.
- RF-19: SI la lista no tiene ítems que coincidan, ENTONCES EL SISTEMA no muestra panel de sugerencias.

### Presupuesto por lista

- RF-20: DONDE el usuario fija un presupuesto en una lista, EL SISTEMA lo guarda como un monto no negativo en la moneda
  de la lista, con las mismas reglas de formato de precios (spec 002 RF-5 a RF-7).
- RF-21: EL SISTEMA trata el presupuesto como opcional: una lista sin presupuesto se comporta como hoy.
- RF-22: DONDE el usuario quita el presupuesto, EL SISTEMA deja la lista sin tope.
- RF-23: SI el presupuesto indicado no es numérico, es negativo o excede el formato permitido, ENTONCES EL SISTEMA lo
  rechaza sin cambiar el presupuesto vigente, con mensaje de validación en español.
- RF-24: DONDE la lista tiene presupuesto, EL SISTEMA lo muestra junto al total pendiente de spec 002 (RF-15 a RF-18).
- RF-25: SI el total pendiente supera el presupuesto, ENTONCES EL SISTEMA muestra un aviso visible junto al total, con
  cuánto se excede; el total igual a presupuesto no cuenta como exceso.
- RF-26: EL SISTEMA no bloquea agregar, editar ni marcar ítems cuando se supera el presupuesto: el aviso es solo
  informativo.
- RF-27: EL SISTEMA reevalúa el aviso tras cada cambio que modifique el total pendiente (agregar, editar, marcar,
  borrar, reiniciar o deshacer), incluidos los que llegan por sincronización.
- RF-28: CUANDO un usuario fija, cambia o quita el presupuesto, EL SISTEMA sube la versión de la lista y lo propaga a
  los demás dispositivos como el nombre y la moneda (spec 002 RF-22 a RF-24).
- RF-29: SI el presupuesto está fijado y la lista no tiene total pendiente (ningún ítem pendiente con precio, spec 002
  RF-17), ENTONCES EL SISTEMA muestra el presupuesto por sí solo en la zona de totales, sin aviso de exceso.
- RF-30: CUANDO el usuario cambia la moneda de la lista, EL SISTEMA conserva el monto del presupuesto sin convertirlo,
  igual que los precios (spec 002, fuera de alcance: conversión de moneda).

### Interacción con la compra semanal (spec 003)

- RF-31: CUANDO se duplica una lista, EL SISTEMA ordena todos los ítems de la copia (que nacen como pendientes, spec 003
  RF-11) por la posición que tenían en la original, y copia el presupuesto; la moneda ya se hereda (spec 003 RF-13). El
  autocompletado no se copia porque se calcula de los ítems de cada lista.
- RF-32: CUANDO se exporta la lista como texto, EL SISTEMA ordena los ítems con el orden manual (pendientes primero y
  comprados después) en lugar del orden de creación (spec 003 RF-22a).
- RF-33: DONDE la lista tiene presupuesto, EL SISTEMA no lo añade al texto exportado: el texto sigue con nombre, ítems y
  total pendiente (spec 003 RF-23).
- RF-34: CUANDO se reinicia la compra o se deshace el reinicio, EL SISTEMA devuelve los ítems desmarcados a su posición
  manual dentro de pendientes.

## Requisitos no funcionales

- **Sincronización**: orden y presupuesto se propagan con el intervalo de 3–4 s de spec 001; la sincronización sigue
  siendo solo lectura.
- **Cotas de peticiones** (spec 001, "Límite de peticiones"): un reordenamiento es una escritura bajo el límite de
  escrituras existente (120/min por IP); pulsaciones seguidas de subir/bajar sobre un mismo ítem se agrupan en una sola
  escritura, de modo que mover un ítem varios lugares no agota el cupo ni llega desordenado. La petición tiene un tope
  de ítems por operación. El autocompletado no hace peticiones por tecla, porque se calcula en el cliente con los ítems
  activos ya cargados.
- **Avisos y componentes nuevos**: llevan `id` o clase semántica propia y no reutilizan `bg-green-50`, `bg-gray-100`,
  `bg-amber-50`, `bg-blue-50`, `bg-sky-50`, `bg-teal-50` ni `bg-red-50`, reservadas para los tests e2e (`AGENTS.md`). El
  aviso de presupuesto excedido no usa `bg-amber-50` ni `bg-red-50`.
- **Dependencias**: si el arrastre táctil exige una librería nueva, se justifica en el plan con versión exacta
  (constitución 1).
- **Accesibilidad**: targets de 44 px, contraste (nunca `text-gray-400` sobre blanco), operación completa por teclado y
  avisos anunciados a lectores de pantalla; axe sin violaciones a 375 y 1280 px.
- **Mobile-first**: arrastrar no debe romper el scroll vertical del celular (asa dedicada).
- **Atomicidad**: un reordenamiento deja todos los ítems con un orden consistente o no cambia nada.
- **Idioma**: interfaz y avisos en español; código en inglés.

## Casos límite

- **Lista con más de 200 ítems** (posible tras duplicar, spec 003 RF-16): reordenar sigue funcionando.
- **Reordenar un ítem y que otro dispositivo lo borre antes**: se ignora el movimiento (RF-8).
- **Dos ítems con el mismo nombre** (spec 001 RF-17): el autocompletado sugiere el nombre una sola vez.
- **Nombre con tildes o mayúsculas**: "Café" y "cafe" coinciden para sugerir y cuentan como un solo nombre (RF-12); al
  elegir se usa el del ítem más reciente.
- **Ítem borrado y restaurado con "Deshacer"**: vuelve a su lugar (RF-4a); si el ítem que le seguía ya no existe, queda
  al final de los pendientes.
- **Presupuesto en 0**: es válido; cualquier total pendiente mayor a 0 lo excede.
- **Presupuesto sin ítems con precio**: se muestra sin aviso (RF-29).
- **Reiniciar la compra con presupuesto**: el total pendiente sube y el aviso se reevalúa (RF-27).
- **Sin red**: reordenar y fijar presupuesto fallan con el aviso de desconexión existente (spec 001 RF-26); el
  autocompletado funciona con los ítems ya cargados.
- **Lista borrada desde otro dispositivo**: reordenar o fijar presupuesto responde con el 404 uniforme (spec 001 RF-27).
- **Recargar durante un arrastre**: el movimiento no confirmado se pierde sin dejar la lista inconsistente.

## Fuera de alcance

- Categorías, pasillos nombrados o agrupación automática de ítems.
- Mover ítems comprados a mano o mezclar comprados con pendientes.
- Catálogo global de productos o sugerencias entre listas, y sugerencias desde ítems borrados (las lápidas no se
  exponen, constitución 7).
- Aprendizaje de frecuencia, ordenar sugerencias por uso o historial de compras.
- Presupuesto por ítem, por período o con conversión de moneda.
- Bloquear compras al superar el presupuesto, o notificaciones push del exceso.
- Compartir con Web Share API y QR (queda para una spec aparte), purga de listas por cron (choca con constitución 5) y
  modo oscuro.

## Criterios de finalización

- [ ] RF-1 a RF-34 (incluidos RF-2a, RF-4a y RF-8a) verificados con tests Pest (API y persistencia) y Playwright (capa
  de cliente).
- [ ] El orden manual y el presupuesto se sincronizan entre dos dispositivos.
- [ ] Reordenar funciona con arrastre táctil, con ratón y solo con teclado.
- [ ] La duplicación conserva orden y presupuesto; el texto exportado sigue el orden manual.
- [ ] Los ítems previos al despliegue se ven en el mismo orden tras la migración.
- [ ] `php artisan test`, `npx playwright test` y `vendor/bin/pint` en verde; axe sin violaciones a 375 y 1280 px.
- [ ] `CACHE_VERSION` de `public/sw.js` subido si cambia el shell cacheado.
- [ ] `docs/roadmap.md`, `docs/schema.md` y `docs/app-flow.md` actualizados y `validation.md` generado en
  `/sdd:validate`.

## Dudas abiertas

- [NEEDS CLARIFICATION: ubicación de la edición del presupuesto en la interfaz; se resuelve en el plan, como la de las acciones de spec 003]

## Clarificaciones

### Fase 2 — spec inicial

- P1 (agrupamiento): reordenar, autocompletado y presupuesto en una sola spec; Compartir/QR queda aparte por su
  dependencia nueva, la purga por cron choca con la constitución 5 y el modo oscuro es solo estético.
- P2 (reordenar y grupos): se mantiene "pendientes primero, comprados después" y el orden manual aplica dentro de cada
  grupo.
- P3 (concurrencia al reordenar): gana el último en llegar, sin error ni aviso.
- P4 (fuente del autocompletado): los ítems de la propia lista, sin duplicados. Se había elegido incluir los borrados,
  pero la revisión de consistencia lo descartó: el cliente no recibe sus nombres (spec 001 RF-24) y exponerlos choca con
  la constitución 7; quedan solo los ítems activos, pendientes y comprados.
- P5 (presupuesto): se compara con el total pendiente de spec 002; el aviso es informativo y no bloquea.
- P6 (interacción con spec 003): duplicar conserva orden y presupuesto, exportar sigue el orden manual y el
  autocompletado no se copia.

### Fase 2 — revisión de consistencia

- Esta spec corrige spec 001 RF-18 y spec 003 RF-11 y RF-22a: el orden de los ítems pasa de "fecha de creación" a una
  posición por ítem, y el delta de sincronización la lleva.
- Amplía el fuera de alcance de spec 002 ("objetivo de gasto, control de gastos"): el presupuesto es opcional e
  informativo, sin conversión de moneda.
- Al deshacer un borrado el ítem recupera su posición (RF-4a); la decisión se tomó por defecto y puede revertirse en
  `/sdd:clarify`.
- Mínimo de 2 caracteres y máximo de 5 sugerencias (RF-11): valores propuestos, pendientes de confirmación.
