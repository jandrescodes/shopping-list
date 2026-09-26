# Spec 002 — Precios de ítems, moneda de la lista y cantidad editable

**Estado:** propuesta

## Contexto y objetivo

Una lista de compras que solo dice _qué_ falta no ayuda a controlar el gasto. Quien va al mercado necesita saber cuánto va a pagar antes de llenar el carrito, y quien arma la lista desde casa quiere dejar anotado el precio que ya consultó para que otra persona no vuelva a buscarlo. Esta feature permite anotar un precio opcional por ítem, define una moneda por lista y muestra el total pendiente: la suma de los precios de los ítems que aún no se han comprado.

El precio es un dato _acotado_ de cada ítem, no un sistema de cuentas: no hay conversión entre monedas, ni historial, ni presupuesto mensual.

Al implementar esta feature se comprobó que la **cantidad** del ítem tiene el backend completo desde spec 001 (columna, validación, recurso, sincronización) pero **ninguna entrada en la interfaz**: el formulario de alta solo envía nombre y quién agrega, y la edición en línea solo cambia el nombre, así que un usuario no puede anotar ni corregir una cantidad desde la pantalla. El alcance se amplía para exponer ese campo, sin tocar su modelo ni su validación.

## Usuarios / actores

No aparecen actores nuevos. Quien tiene el enlace de la lista puede leer y modificar precios y moneda, igual que el nombre de la lista o el estado de un ítem: es coherente con la constitución (el `slug` es la única llave de acceso) y con spec 001.

## Historias de usuario

- H1: Como quien arma la lista quiero anotar el precio de un ítem para no buscarlo otra vez en el mercado.
- H2: Como quien va al mercado quiero ver el total de lo que me falta por comprar para no llevarme sorpresas con la billetera.
- H3: Como miembro de la familia quiero ajustar el precio de un ítem cuando el supermercado me da otro valor.
- H4: Como miembro de la familia quiero que la moneda de la lista sea la que uso yo, y no la de otra persona.
- H5: Como miembro de la familia quiero que un precio que anoté llegue a los demás dispositivos, para que la persona que está en el mercado vea lo mismo que yo.
- H6: Como quien arma la lista quiero anotar la cantidad de un ítem al agregarlo o corregirla después, para que la lista diga cuánto llevar y no solo qué llevar.

## Requisitos funcionales (criterios de aceptación en EARS)

### Precios de los ítems

- RF-1: DONDE el usuario indica un precio al agregar un ítem, EL SISTEMA lo guarda
  asociado a ese ítem.
- RF-2: EL SISTEMA trata el precio como opcional: un ítem puede quedar sin precio, y
  en ese caso no se guarda ningún monto para él.
- RF-3: DONDE el usuario indica el precio de un ítem ya creado, EL SISTEMA guarda
  ese precio aplicando las mismas reglas de validación que al agregarlo.
- RF-4: DONDE el usuario borra el precio de un ítem existente, EL SISTEMA deja a
  ese ítem sin precio; no guarda el valor cero en su lugar.
- RF-5: EL SISTEMA acepta un precio no negativo de hasta 8 dígitos enteros y 2
  decimales, e interpreta el cero como un precio válido y distinto de la ausencia
  de precio.
- RF-6: SI el precio indicado no es numérico, es negativo, supera los 2 decimales
  o excede el máximo admitido, ENTONCES EL SISTEMA rechaza la operación e informa
  del error de validación.
- RF-7: EL SISTEMA acepta coma y punto como separador decimal, e interpreta
  `12,50` y `12.50` como el mismo monto.
- RF-8: DONDE un ítem tiene precio, EL SISTEMA lo muestra junto al nombre del
  ítem, solo como número y sin el símbolo de la moneda; DONDE no lo tiene, EL
  SISTEMA no muestra ningún marcador en su lugar.
- RF-9: EL SISTEMA interpreta el precio indicado como el monto total de esa línea
  de la lista, no como un precio por unidad.

### Moneda de la lista

- RF-10: EL SISTEMA asocia a toda lista una moneda: un texto libre de 1 a 5
  caracteres, sensible a mayúsculas, con el valor por defecto `Bs` en toda lista
  nueva.
- RF-11: DONDE el usuario cambia la moneda de su lista, EL SISTEMA la guarda y la
  muestra en el encabezado de esa lista, junto al nombre.
- RF-12: SI la moneda indicada queda vacía tras recortar los espacios exteriores o
  supera los 5 caracteres, ENTONCES EL SISTEMA rechaza el cambio e informa del
  error de validación.
- RF-13: CUANDO un usuario crea una lista, EL SISTEMA la crea con la moneda por
  defecto; el usuario ajusta la moneda después, desde la vista de la lista.
- RF-14: EL SISTEMA muestra el símbolo de la moneda en el encabezado y en el
  total, y no lo repite en cada fila de ítem.

### Total pendiente

- RF-15: MIENTRAS una lista está abierta, EL SISTEMA muestra el total pendiente: la
  suma de los precios de los ítems **no comprados** que tienen precio.
- RF-16: DONDE hay al menos un ítem no comprado con precio, EL SISTEMA muestra el
  total acompañado del símbolo de la moneda de la lista.
- RF-17: SI ningún ítem no comprado tiene precio, ENTONCES EL SISTEMA no muestra el
  total.
- RF-18: EL SISTEMA recalcula y muestra el total de nuevo tras cada cambio de
  precio, de estado comprado o del conjunto de ítems, sin que el usuario recargue
  la página.
- RF-19: EL SISTEMA sigue mostrando el precio individual de los ítems comprados,
  aunque esos precios no sumen en el total pendiente.
- RF-20: CUANDO el usuario borra el precio del último ítem no comprado que lo
  tenía, ENTONCES EL SISTEMA deja de mostrar el total.

### Propagación entre dispositivos

- RF-21: CUANDO un usuario cambia el precio de un ítem, EL SISTEMA propaga el
  cambio a los demás dispositivos con esa lista abierta, con la misma garantía de
  plazo que el resto de cambios de ítems (spec 001, RF-22 y RF-24).
- RF-22: CUANDO un usuario cambia el nombre o la moneda de una lista, EL SISTEMA
  propaga ese cambio a los demás dispositivos con esa lista abierta, con la misma
  garantía de plazo. Esta propagación del nombre no existía en spec 001 y queda
  cubierta desde RF-1.
- RF-23: EL SISTEMA incluye el nombre y la moneda vigentes de la lista en su
  respuesta de sincronización, junto al cursor de versión, en cada consulta.
- RF-24: DONDE un dispositivo con la lista abierta recibe por sincronización un
  nombre o una moneda distintos de los que tiene en pantalla, EL SISTEMA los
  aplica a la vista.

### Preservación de datos al deshacer

- RF-25: DONDE un usuario deshace la eliminación de un ítem, EL SISTEMA lo vuelve a
  crear conservando su nombre, cantidad, quién lo agregó, su estado de comprado y
  su precio.

### Cantidad editable en la interfaz

- RF-26: DONDE el usuario agrega un ítem, EL SISTEMA le ofrece además un campo
  opcional para indicar su cantidad, y la guarda aplicando las reglas de
  validación de spec 001 (RF-11).
- RF-27: DONDE el usuario edita la cantidad de un ítem ya creado desde la lista,
  EL SISTEMA la guarda aplicando esas mismas reglas de validación.
- RF-28: DONDE un ítem tiene cantidad, EL SISTEMA la muestra junto a su nombre;
  DONDE no la tiene, EL SISTEMA no muestra ningún marcador en su lugar.

## Requisitos no funcionales

- **Sincronización**: el cambio de precio, de nombre y de moneda se propaga bajo
  el mismo plazo de 5 s y con el mismo intervalo de 3–4 s ya fijados en spec 001;
  la sincronización sigue siendo solo lectura y nunca crea ítems.
- **Cotas de peticiones**: cambiar la moneda de una lista cuenta como escritura y
  consume la cota de escrituras de spec 001 (120/min por IP); no se abre ninguna
  cota nueva.
- **Formato de importes**: los importes se muestran con coma como separador decimal
  y con separador de miles, independientemente del locale del navegador, para que
  el separador coincida siempre con el del símbolo de la moneda.
- **Precisión aritmética**: el total pendiente se calcula sin error de redondeo de
  coma flotante, de modo que la suma de una lista con muchos importes coincide con
  la suma de sus precios.
- **Plataformas**: hereda spec 001 — Chrome y Safari móviles recientes,
  mobile-first, usable desde 320 px de ancho.
- **Idioma**: hereda spec 001 — interfaz en español; los mensajes de validación de
  precio y de moneda se escriben en español.

## Casos límite

- **Ítem agregado sin precio**: se muestra sin monto, no deja marcador vacío y no
  altera el total.
- **Precio `0`**: es un precio válido, se muestra en la fila y hace que el total se
  muestre aunque no aporte importe.
- **Espacios alrededor del precio** (`" 12,50 "`): se recortan y se aceptan; los
  espacios interiores o un separador de miles en el valor entregado (`"1.234,50"`)
  se rechazan, porque el campo espera un número simple.
- **Precio con más de 2 decimales** (`"1.999"`): se rechaza con error de validación.
- **Precio negativo**: se rechaza con error de validación.
- **Precio de un ítem borrado desde otro dispositivo**: el delta lo trae sin precio
  y el cliente retira el monto de la fila.
- **Precio editado mientras el ítem se borra en otro dispositivo**: prevalece la
  eliminación (heredado de spec 001).
- **Precio indicado con la lista ya en 200 ítems**: se rechaza por el límite de
  spec 001 (RF-20), no por el precio.
- **Cambio de moneda contra una lista ya eliminada**: responde "no encontrado",
  igual que renombrar una lista eliminada (spec 001, RF-27).
- **Cambio de moneda con espacios** (`"Bs "`): se recorta y se guarda como `Bs`.
- **Cambio de moneda a un valor que solo difiere en mayúsculas** (`bs`): se guarda
  tal cual; la moneda es texto libre sensible a mayúsculas y no se normaliza.
- **Todos los ítems con precio están comprados**: no se muestra total (no hay no
  comprados con precio), pero cada fila sigue mostrando su precio.
- **Total con precios de distintas magnitudes**: un solo importe, sin desglose por
  ítem; el desglose es lo que ya muestra cada fila.
- **Deshacer la eliminación de un ítem con precio**: conserva el precio, con un
  identificador nuevo (spec 001, RF-25 y RF-19).
- **Edición de precio en curso mientras llega una sincronización**: la
  sincronización no pisa el valor que el usuario está escribiendo, igual que con el
  nombre del ítem (spec 001, RF-22).
- **Cantidad con solo espacios exteriores** (`"   "`): se recorta y el ítem queda
  sin cantidad; no se guarda un valor vacío.
- **Cantidad de más de 50 caracteres**: se rechaza con error de validación
  (spec 001, RF-11), igual que por cualquier otro camino.
- **Cantidad editada mientras llega una sincronización**: no se pisa el valor que
  el usuario está escribiendo, igual que con el nombre del ítem (spec 001, RF-22).
- **Editar solo la cantidad de un ítem**: su nombre y su estado comprado quedan
  intactos (spec 001, RF-25, escritura campo por campo).
- **Ítem sin cantidad**: la fila no muestra ningún chip ni espacio en blanco en su
  lugar (RF-28).

## Fuera de alcance

- Conversión entre monedas, tipo de cambio o precios en más de una moneda a la vez.
- Precio por unidad o cálculo a partir de cantidad y precio unitario (RF-9).
- Presupuesto mensual, objetivo de gasto, control de gastos o historial de lo
  gastado.
- Cualquier pantalla de resumen, gráfico o exportación de totales.
- Filtrar, buscar, ordenar o agrupar por precio.
- Editar el precio de varios ítems a la vez.
- Un campo de precio en la lista de "mis listas" del home.
- Catálogo de unidades de medida (kg, l, docena), conversión entre unidades o
  validación numérica de la cantidad: sigue siendo texto libre (spec 001, RF-11).
- Ingresar la moneda al crear la lista (RF-13) o proponerla desde el navegador.
- Un subtotal de lo ya comprado, además del total pendiente.
- Redondeo configurable o una cantidad de decimales distinta de 2.

## Criterios de finalización

- Todos los RF con test automatizado en verde: Pest para API y persistencia bajo
  `php artisan test`, y tests de navegador con `npx playwright test` para el
  comportamiento de cliente (RF-8, RF-11, RF-15 a RF-17, RF-20, RF-22, RF-24,
  RF-25, RF-26 a RF-28).
- Ningún test existente se relaja para dar por buena la feature: los asserts que
  enumeran exactamente las claves de un ítem o de una respuesta de sincronización
  se actualizan para incluir los campos nuevos, conservando la exactitud de la
  aserción.
- Un test cubre explícitamente que un precio con separador coma y otro con punto
  producen el mismo monto persistido (RF-7).
- Un test cubre explícitamente que un precio ausente no se persiste como `0.00`
  (RF-2, RF-4).
- Un test de navegador cubre que un cambio de moneda o de nombre hecho en un
  dispositivo aparece en un segundo dispositivo con la lista abierta, vía polling
  (RF-22, RF-24).
- Un test de navegador cubre que deshacer una eliminación conserva el precio
  (RF-25).
- Un test de navegador cubre que la cantidad se puede anotar al agregar un ítem
  y corregirla después en la fila, que editar **solo** la cantidad deja el nombre
  y el estado comprado intactos, y que un ítem sin cantidad no deja marcador
  (RF-26, RF-27, RF-28). A nivel de API, guardar, validar y propagar la cantidad
  ya están cubiertos desde spec 001: esta feature no añade cobertura de backend,
  solo la ruta de interfaz que los dispara.
- `php artisan pint` sin cambios pendientes.

## Dudas abiertas

Ninguna.

## Clarificaciones

### Fase 2 — spec inicial

- **Precio por línea, no por unidad**: se aclara en RF-9. "Leche" a `12,50`
  significa que esa línea cuesta `12,50`, no que la unidad cuesta `12,50`. Evita
  tener que modelar cantidad × precio y evita la ambigüedad de leer "2 x 12,50" en
  un campo numérico.
- **Símbolo libre, no códigos ISO**: la moneda es un texto de 1 a 5 caracteres, no
  un catálogo validado. Un código ISO obligaría a una lista cerrada en el sistema
  y a un control desplegable en la interfaz; el símbolo libre resuelve el caso real
  (la familia no siempre compra en la misma moneda) sin mantener esa lista.
- **Moneda por lista, no por ítem**: un total solo tiene sentido si todas las
  líneas comparten unidad. Con moneda por ítem, se mezclarían unidades en una misma
  suma.
- **Solo el total pendiente**: el número útil en la caja es lo que aún no se
  compró. El subtotal de lo comprado se descarta por decisión explícita.
- **`0` es un precio, no una ausencia**: sin esta distinción, un ítem de cortesía
  o de stock previo no se podría representar, y "vaciar el precio" y "poner 0"
  serían la misma operación.
- **Símbolo en encabezado y total, no en cada fila**: con hasta 200 ítems,
  repetir el símbolo en cada fila es ruido y consume el ancho disponible a 320 px.
  El símbolo ya está visible en el encabezado, y cada fila conserva su número.
- **Cambiar la moneda solo desde la vista de lista**: el formulario de creación del
  home sigue teniendo un campo. Quien necesita otra moneda la cambia una vez, en el
  sitio donde va a leerla.
- **Propagar nombre y moneda por la sincronización**: RF-22 cierra un hueco de spec
  001 (el renombrado no llegaba a las pestañas abiertas) que esta feature hace
  inevitable, porque la moneda vive en la lista y no en el ítem.

### Fase 8 — cambio de alcance: cantidad editable

- **La cantidad ya funcionaba en backend; el hueco era solo de interfaz.**
  Verificado sobre el código durante T0: la columna existe desde spec 001,
  `StoreItemRequest` y `UpdateItemRequest` la validan y normalizan,
  `ItemResource` la devuelve, `sync` la propaga y `undoRemove` ya la conserva en
  el deshacer. Lo que no existía era un camino de escritura: el formulario de
  alta solo envía `name` y `added_by`, y la edición en línea solo cambia el
  nombre.
- **Ampliar la spec 002 en vez de abrir una spec 003**: decisión tomada al
  revisar el cambio, no una omisión. El costo es ajustar el título y el "Fuera de
  alcance" de la spec para no contradecirse; el beneficio es no partir una misma
  revisión en dos ciclos de spec/plan/tasks.
- **Alta y edición, las dos**: solo el alta dejaría sin forma de corregir un ítem
  creado sin cantidad, que es el caso más probable — se agrega rápido en el
  mercado y la cantidad se completa después.
- **Los RF nuevos exponen, no redefinen.** RF-26 y RF-27 remiten a las reglas de
  spec 001 (RF-11) en lugar de repetirlas: si la validación de la cantidad
  cambia, cambia en un solo lugar.
- **Sin renumeración.** Los RF nuevos entran al final del bloque como RF-26 a
  RF-28, para que todas las referencias `RF-n` de `plan.md`, `tasks.md` y del
  resto de la spec sigan apuntando a lo mismo.
- **Fuera de alcance acotado, no suprimido**: desaparece la línea que declaraba a
  la cantidad sin entrada en la interfaz y queda en su lugar lo que sigue sin
  hacerse (catálogo de unidades, conversión, validación numérica). El cálculo
  cantidad × precio unitario ya estaba fuera de alcance por RF-9 y ahí no se
  toca.
