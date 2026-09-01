# Spec 001 — Lista de compras compartida

## Contexto y objetivo

La familia necesita una lista de compras que varias personas puedan ver y
actualizar desde su celular, sin la hoja de papel que se pierde ni la fricción de
invitar colaboradores como en Google Keep. Esta funcionalidad permite crear
listas con nombre, gestionar sus ítems (agregar, editar, marcar como comprado,
eliminar) y que los cambios hechos en un dispositivo aparezcan en los demás en
pocos segundos, todo sin cuentas de usuario: quien tiene el enlace de una lista
puede operarla.

## Usuarios / actores

- **Miembro de la familia**: guarda los enlaces de las listas de la casa y las
  usa a diario.
- **Persona ocasional**: recibe el enlace de una lista concreta para ayudar con
  una compra puntual.

No hay distinción técnica entre ambos: el acceso es siempre por el enlace (slug)
de la lista. Quien tiene el enlace puede hacer todo sobre la lista, incluido
renombrarla, vaciar los comprados y eliminarla; es intencional y coherente con la
constitución (el slug es la única llave de acceso).

## Historias de usuario

- H1: Como miembro de la familia quiero crear una lista con nombre para juntar lo
  que falta comprar.
- H2: Como alguien con el enlace de una lista quiero agregar y editar ítems para
  que el resto los vea.
- H3: Como quien va al mercado quiero marcar ítems como comprados con un toque
  para saber qué falta.
- H4: Como miembro de la familia quiero ver los cambios de otros sin recargar la
  página para no comprar de más ni de menos.
- H5: Como quien usa la app quiero instalarla en la pantalla de inicio del
  celular para abrirla como una app.

## Requisitos funcionales (criterios de aceptación en EARS)

### Listas: creación y acceso

- RF-1: CUANDO un usuario envía el nombre de una nueva lista, EL SISTEMA crea la
  lista, le genera un identificador aleatorio opaco de 128 bits con un generador
  criptográficamente seguro (16 bytes aleatorios codificados en base64url sin
  relleno: 22 caracteres del alfabeto `[A-Za-z0-9_-]`, sensible a mayúsculas) que
  actúa como «slug» del segmento de URL `/l/{slug}` (no se deriva del nombre ni
  del `id`), y devuelve el enlace público absoluto de esa lista
  (`{APP_URL}/l/{slug}`).
- RF-2: SI el nombre de la lista queda vacío o supera 60 caracteres tras recortar
  espacios exteriores, ENTONCES EL SISTEMA rechaza la creación e informa del
  error de validación.
- RF-3: CUANDO un usuario abre el enlace público de una lista, EL SISTEMA muestra
  esa lista con sus ítems no comprados y comprados.
- RF-4: SI el slug solicitado no corresponde a ninguna lista, ENTONCES EL SISTEMA
  responde "no encontrado" (404) sin indicar si esa lista existió alguna vez. El
  servidor nunca distingue "eliminada" de "nunca existió": la respuesta 404 es
  idéntica —mismo código, cuerpo y cabeceras— haya existido o no la lista (la
  salvedad de RF-27 la deduce el cliente, no el servidor).
- RF-5: EL SISTEMA no expone en el servidor ninguna pantalla ni endpoint que
  enumere o busque listas; en el servidor solo se llega a una lista por su slug.
  El acceso directo de RF-6 es memoria local del navegador, no una consulta al
  servidor.
- RF-6: CUANDO un usuario abre el enlace de una lista, EL SISTEMA (cliente)
  recuerda ese enlace en el almacenamiento local del navegador y lo ofrece como
  acceso directo en visitas posteriores. Esa memoria persiste hasta que el
  usuario borra los datos del navegador o quita la entrada manualmente. EL
  SISTEMA (cliente): ofrece una acción "quitar de mis listas" por entrada;
  actualiza el nombre mostrado con el que devuelve el servidor al abrir la lista
  con éxito (RF-7); retira la entrada si al abrirla el servidor responde "no
  encontrado" (404); y conserva como máximo las 20 listas abiertas más
  recientes, descartando la más antigua al superar ese número.

### Listas: renombrar y eliminar

- RF-7: CUANDO un usuario con el enlace de una lista cambia su nombre, EL SISTEMA
  guarda el nuevo nombre (mismas reglas de validación que RF-2) conservando el
  mismo slug.
- RF-8: CUANDO un usuario confirma la eliminación de una lista, EL SISTEMA borra
  de forma física la lista junto con todos sus ítems (activos y lápidas), y
  cualquier acceso posterior a ese slug responde "no encontrado" (RF-4).
- RF-9: SI un usuario solicita eliminar una lista, ENTONCES EL SISTEMA exige una
  confirmación explícita antes de borrarla.

### Ítems

- RF-10: CUANDO un usuario agrega un ítem indicando su nombre, EL SISTEMA lo
  añade a la lista en estado "no comprado".
- RF-11: DONDE el usuario indica una cantidad al agregar o editar un ítem, EL
  SISTEMA la guarda como texto libre de hasta 50 caracteres, recortando los
  espacios exteriores; si tras recortar queda vacía, no se guarda cantidad.
- RF-12: DONDE el usuario indica quién agrega el ítem, EL SISTEMA guarda ese
  texto libre de hasta 50 caracteres sin asociarlo a ninguna cuenta, recortando
  los espacios exteriores; si tras recortar queda vacío, no se guarda ese dato.
- RF-13: SI el nombre del ítem queda vacío o supera 100 caracteres tras recortar
  espacios exteriores, ENTONCES EL SISTEMA rechaza la operación e informa del
  error de validación.
- RF-14: CUANDO un usuario edita el nombre o la cantidad de un ítem existente, EL
  SISTEMA guarda los cambios aplicando las mismas reglas de validación.
- RF-15: CUANDO un usuario marca un ítem como comprado o como no comprado, EL
  SISTEMA guarda el nuevo estado de inmediato, sin paso de confirmación.
- RF-16: CUANDO un usuario elimina un ítem, EL SISTEMA lo marca como eliminado
  (borrado lógico), deja de mostrarlo y conserva el registro como lápida
  (tombstone) para la sincronización. EL SISTEMA no purga esas lápidas de forma
  automática; se retiran a mano con el comando de mantenimiento
  `php artisan items:purge-tombstones --before=<fecha>` (ver "Fuera de alcance").
- RF-17: EL SISTEMA admite varios ítems con el mismo nombre en una misma lista;
  un nombre repetido no se trata como duplicado.
- RF-18: EL SISTEMA devuelve y muestra los ítems no comprados primero y los
  comprados después; dentro de cada grupo, ordenados por fecha de creación
  ascendente. En la carga completa de una lista (RF-3 y RF-24 sin cursor válido)
  el orden lo fija el servidor y el cliente lo respeta sin recalcularlo. Al
  fusionar un delta de sincronización (RF-24 con cursor), que no incluye posición,
  el cliente recoloca los ítems afectados aplicando esa misma regla de orden. Los
  comprados se diferencian visualmente (p. ej. tachados).
- RF-19: CUANDO un usuario usa la acción "limpiar comprados", EL SISTEMA elimina
  (borrado lógico) todos los ítems que consten como comprados en la base de datos
  en el momento de procesar la operación —no según lo que el cliente creyera—, en
  una sola pasada, previa confirmación explícita. SI no hay ningún ítem comprado,
  ENTONCES la operación no cambia nada y responde con éxito.
- RF-20: SI una lista ya tiene 200 ítems activos (no eliminados), ENTONCES EL
  SISTEMA rechaza el alta que la dejaría en 201 e informa de que se alcanzó el
  límite. Se permite llegar a 200 inclusive.
- RF-21: CUANDO un usuario indica por primera vez en un dispositivo quién es, EL
  SISTEMA (cliente) recuerda ese nombre y lo propone, ya rellenado y editable, al
  agregar ítems.

### Sincronización entre dispositivos

- RF-22: MIENTRAS un usuario tiene una lista abierta y visible, EL SISTEMA
  (cliente) consulta al servidor los cambios de esa lista de forma periódica, sin
  que el usuario recargue la página. MIENTRAS la pestaña está oculta (Page
  Visibility), EL SISTEMA pausa la consulta y la reanuda con una consulta
  inmediata al volver al primer plano.
- RF-23: CUANDO otro dispositivo agrega, edita, marca o elimina un ítem de una
  lista abierta, EL SISTEMA refleja ese cambio en los demás dispositivos con la
  lista abierta en 5 segundos o menos en condiciones normales de red.
- RF-24: CADA lista mantiene un contador de versión monótono. EL SISTEMA lo
  incrementa de forma atómica en cada escritura sobre la lista o sus ítems (alta,
  edición, marca/desmarca, borrado lógico, "limpiar comprados", renombrado) y
  sella con el valor resultante cada fila afectada, incluidas las lápidas.
  CUANDO el cliente pide los cambios enviando el "cursor" (el valor del contador
  que el servidor le entregó en la consulta anterior), EL SISTEMA devuelve los
  ítems cuya versión es estrictamente mayor que el cursor —los creados o
  modificados—, los identificadores (solo el `id`, nunca `name`, `added_by` ni
  marcas de tiempo) de los ítems con borrado lógico en ese intervalo, y el valor
  actual del contador como nuevo cursor. El cursor es un
  valor opaco emitido por el servidor; el cliente lo reenvía tal cual y nunca lo
  interpreta ni lo deriva de su reloj. SI el cursor falta, está malformado, es
  mayor que el contador actual o no corresponde a la lista pedida, ENTONCES EL
  SISTEMA responde con el estado completo de la lista (solo ítems activos, sin
  lápidas) y el valor actual del contador como cursor.
- RF-25: SI dos usuarios modifican el mismo ítem —o renombran la misma lista
  (RF-7)— casi a la vez, ENTONCES EL SISTEMA aplica las peticiones en el orden en
  que llegan al servidor, serializadas por transacción de base de datos, y campo
  por campo —cada petición solo escribe los campos que incluye—, conservando el
  último valor recibido de cada campo, sin bloqueo ni aviso de conflicto. EL
  SISTEMA (cliente) envía en cada edición solo los campos que cambian, nunca el
  objeto completo.
- RF-26: SI el cliente pierde la conexión, ENTONCES EL SISTEMA (cliente) sigue
  mostrando la última versión conocida de la lista y reanuda la sincronización al
  recuperar la conexión. Las operaciones de escritura requieren conexión: sin
  ella, la acción falla con aviso y no se encola para más tarde.
- RF-27: SI la lista abierta fue eliminada desde otro dispositivo, ENTONCES la
  siguiente consulta de sincronización responde "no encontrado" y EL SISTEMA
  (cliente), que la tenía abierta, informa de que la lista ya no existe. El
  servidor responde igual que ante un slug inexistente (RF-4); es el cliente
  quien deduce el aviso por tenerla abierta.

### PWA y acceso

- RF-28: EL SISTEMA se puede instalar en la pantalla de inicio de un celular como
  PWA, con un manifest válido que incluya nombre, color de tema e iconos de al
  menos 192×192 y 512×512 px.
- RF-29: MIENTRAS no haya conexión y la app se haya abierto al menos una vez con
  conexión, EL SISTEMA carga su interfaz y sus assets estáticos desde la caché
  del service worker. SI nunca se abrió con conexión, EL SISTEMA muestra una
  página offline mínima.
- RF-30: EL SISTEMA no solicita usuario, contraseña ni registro en ningún punto
  para ver u operar una lista. El código base no incluye el andamiaje de
  autenticación de Laravel (modelo `User`; tablas `users`, `sessions`,
  `password_reset_tokens` y `personal_access_tokens`; la dependencia
  `laravel/sanctum`; rutas protegidas por `auth`): se retira antes de implementar
  el resto de la spec.
- RF-31: EL SISTEMA no impone un límite de personas ni de dispositivos que puedan
  usar el enlace de una lista.

### Seguridad del contenido

- RF-32: EL SISTEMA trata `name`, `quantity` y `added_by` como texto plano en
  todo momento: los almacena sin interpretarlos y los presenta —en las vistas
  Blade y en el cliente— como texto, nunca como HTML. El cliente usa enlace de
  texto (`x-text` / `textContent`), nunca `x-html` ni `innerHTML`, para el
  contenido introducido por el usuario.

## Requisitos no funcionales

- **Sincronización**: la propagación de un cambio no supera los 5 s en
  condiciones normales de red; el intervalo de consulta del cliente es de 3–4 s
  (pausado mientras la pestaña está oculta, RF-22). La respuesta "sin novedades"
  devuelve solo el cursor, sin ítems. El punto de corte es el contador de versión
  de la lista, gestionado íntegramente por el servidor; no interviene ningún
  reloj, ni del cliente ni de la base de datos.
- **Slug no adivinable**: 128 bits de entropía (16 bytes) de un generador
  criptográficamente seguro, codificados en base64url sin relleno (22 caracteres,
  sensible a mayúsculas); no enumerable ni derivable del `id`. La coincidencia del
  slug en la ruta es exacta y sensible a mayúsculas.
- **Plataformas**: navegadores móviles modernos (Chrome y Safari de los últimos
  ~2 años). Diseño mobile-first, usable desde 320 px de ancho.
- **Hosting**: funciona en hosting compartido — sin procesos persistentes, sin
  WebSockets, sin colas ni schedulers.
- **Idioma**: interfaz en español. `APP_LOCALE=es` y `APP_FALLBACK_LOCALE=es`; se
  publica `lang/es/validation.php` y los Form Requests solo añaden `messages()`
  propios donde el texto por defecto no baste.
- **Frontend**: Blade + Alpine.js (versión pineada en `package.json`, incluida en
  el build de Vite junto a `resources/js/list.js`). El plan justifica Alpine
  frente a vanilla en "Decisiones técnicas".
- **HTTPS**: en producción la app se sirve exclusivamente por HTTPS (requisito del
  service worker y del manifest, RF-28/29); las URLs generadas por el servidor
  fuerzan esquema `https`. Se verifica en el despliegue.
- **No indexable**: las páginas de lista (`/l/{slug}`) responden con cabecera
  `X-Robots-Tag: noindex, nofollow` y el sitio publica un `robots.txt` con
  `Disallow: /l/`. El slug es la única llave de acceso (constitución 4) y no debe
  llegar a un índice de búsqueda.
- **Límite de peticiones**: por IP, respondiendo 429 al exceder — crear lista:
  10/hora; resto de escrituras (ítems, marcar, editar, renombrar, limpiar):
  120/min; sincronización (GET): 60/min. Valores holgados para uso familiar real,
  cota defensiva para el hosting compartido (constitución 5).

## Casos límite

- **Lista sin ítems**: se muestra un estado vacío que invita a agregar el primer
  ítem.
- **Colisión de slug al generar**: EL SISTEMA reintenta hasta obtener uno libre.
- **Cursor de sincronización ausente, malformado, mayor que el contador actual o
  de otra lista**: lo valida y lo resuelve el servidor, que responde con el
  estado completo (solo ítems activos, sin lápidas); el cliente solo reutiliza el
  valor de cursor que le devolvió el servidor, nunca lo calcula.
- **Escrituras concurrentes sobre la misma lista**: el incremento atómico del
  contador de versión da un valor distinto a cada escritura, así que no hay
  empates de versión. El cliente fusiona por `id`, de modo que recibir un ítem
  repetido entre dos consultas es inocuo.
- **Escritura contra una lista ya eliminada** (renombrar, agregar ítem, marcar):
  responde "no encontrado"; el cliente lo trata igual que RF-27.
- **Ítem creado y eliminado entre dos consultas de sync**: llega en `deleted_ids`
  con un `id` que el cliente nunca tuvo; el cliente lo ignora (fusiona por `id`).
- **Cursor de una lista inactiva mucho tiempo**: el cursor (contador de versión)
  no caduca; sigue siendo válido mientras no supere el contador actual.
- **Sincronización con la lista llena (200 ítems)**: la sincronización es de solo
  lectura y nunca crea ítems; el tope de RF-20 solo se evalúa en el alta.
- **Slug mal copiado** (mayúsculas cambiadas, puntuación final pegada desde un
  chat): la coincidencia es exacta y sensible a mayúsculas, no hay normalización;
  un slug alterado responde 404 (RF-4).
- **Alta de ítem reintentada tras un corte de red**: puede crear un duplicado; se
  acepta, coherente con RF-17 (no hay clave de idempotencia en v1).
- **"Limpiar comprados" sin ítems comprados**: no cambia nada y responde con
  éxito (RF-19).
- **Dos altas concurrentes al llegar a 200**: pueden dejar 201 ítems activos; se
  acepta por ser cota de protección, no de negocio (RF-20).
- **Primera carga sin conexión** (service worker aún sin caché): se muestra una
  página offline mínima (RF-29).
- **Ítem editado o marcado/desmarcado en un dispositivo y eliminado en otro**:
  prevalece la eliminación.
- **Marca / desmarca rápida del mismo ítem por dos personas**: prevalece la
  última escritura recibida (RF-25).
- **Nombres con emoji, acentos o espacios interiores**: se conservan tal cual
  tras recortar solo los espacios exteriores.
- **Cantidad o "quién agregó" vacíos**: el ítem se muestra sin esos datos y sin
  placeholder.
- **Lista eliminada mientras otro dispositivo la tiene abierta**: RF-27.
- **Ítems comprados**: se mantienen en la lista, ordenados al final y tachados
  (RF-18); solo desaparecen con "limpiar comprados" (RF-19).
- **Lista llena (200 ítems activos)**: agregar falla con aviso; marcar, editar y
  eliminar siguen disponibles; "limpiar comprados" libera cupo (RF-20).

## Fuera de alcance

- Categorías de ítems (lácteos, limpieza, etc.).
- Autenticación, cuentas, roles, permisos o invitaciones de colaboradores.
- Historial o estadísticas de compras pasadas; papelera o recuperación de ítems
  que el usuario eliminó.
- Notificaciones push (esta feature solo usa polling).
- Pantalla global con todas las listas o buscador de listas.
- Modo oscuro.
- Resolución de conflictos por fusión: se usa siempre la última escritura.
- App nativa o publicación en tiendas de aplicaciones.
- Escritura sin conexión: cola offline o sincronización diferida. En v1 las
  acciones de escritura requieren conexión y fallan con aviso si no la hay.
- Idempotencia de operaciones o deduplicación de reintentos de red.
- Purga automática o programada de lápidas de sincronización (ítems con borrado
  lógico); se retiran a mano con `php artisan items:purge-tombstones --before=<fecha>`
  en mantenimiento, sin scheduler ni cron.

## Criterios de finalización

- Todos los RF con test automatizado en verde: Pest/PHPUnit para la API y la
  persistencia; tests de navegador con Playwright (Pest 4 browser testing, bajo
  `php artisan test`) para el comportamiento de cliente (RF-6, RF-21, RF-22,
  RF-23, RF-26, RF-27). RF-28 y RF-29 se cubren con tests de contenido (manifest
  servido y válido con `name`, `icons` 192 y 512, `theme_color`, `display`; los
  ficheros de icono referenciados existen y se sirven con `Content-Type: image/png`;
  `sw.js` servido con MIME de JS) más la verificación manual de Lighthouse
  indicada abajo.
- `APP_URL` fijada al dominio real en el despliegue: un test comprueba que el
  enlace devuelto por RF-1 es absoluto y arranca por `APP_URL`.
- Demo manual en celular: crear lista → abrir el enlace en dos dispositivos →
  agregar, marcar, editar y borrar un ítem → ver cada cambio en el otro
  dispositivo en ≤ 5 s.
- Instalación como PWA verificada en Chrome Android (Lighthouse: "installable").
- `php artisan pint` sin cambios pendientes.

## Dudas abiertas

Ninguna.

## Resoluciones registradas

### Fase 2 — spec inicial

- **"Quién agregó" recordado por dispositivo** (RF-21): se pide una vez, el
  cliente lo guarda y lo autocompleta editable. Prioriza la cero fricción sin
  crear cuentas.
- **Ítems comprados visibles al final, tachados** (RF-18) + acción "limpiar
  comprados" (RF-19): se ven de un vistazo qué ya está y qué falta; la limpieza
  es una acción deliberada, no automática.
- **Tope de 200 ítems activos por lista** (RF-20): margen amplio para uso
  familiar real y cota defensiva para el hosting compartido; al llegar al límite
  solo se bloquea agregar.

### Fase 3 — clarificación

- **Slug** (RF-1): token aleatorio opaco ≥128 bits, no derivado del nombre ni del
  `id`. "Slug" solo nombra el segmento de la URL.
- **Cursor de sincronización** (RF-24, RNF): valor opaco emitido por el servidor;
  el cliente lo reenvía sin interpretarlo. El corte lo fija el servidor con su
  reloj/BD, con semántica inclusiva en el instante del cursor (reenvía cambios
  del mismo tick; el cliente fusiona por `id`). Cursor ausente/inválido/de otra
  lista ⇒ estado completo.
- **Última escritura** (RF-25): por orden de llegada al servidor y campo por
  campo; cada petición solo escribe los campos que envía.
- **RF-4 vs RF-27**: el 404 genérico es la regla; el servidor no distingue
  "eliminada" de "inexistente". El aviso "la lista ya no existe" lo deduce el
  cliente que la tenía abierta.
- **RF-5 vs RF-6**: RF-5 prohíbe enumerar/buscar listas en el servidor; RF-6 es
  memoria local del navegador, no un endpoint.
- **Orden de ítems** (RF-18): no comprados antes que comprados; dentro de cada
  grupo por fecha de creación ascendente; lo fija el servidor.
- **Trim de cantidad y "quién agregó"** (RF-11, RF-12): se recortan espacios
  exteriores; si queda vacío, no se guarda el dato.
- **Persistencia en cliente** (RF-6, RF-21): almacenamiento local del navegador;
  dura hasta que el usuario borra los datos del navegador.
- **Límite de 200** (RF-20): se permite llegar a 200 inclusive; se rechaza el
  alta que dejaría 201. Dos altas concurrentes pueden dejar 201 (aceptado).
- **Escritura sin conexión** (RF-26): fuera de alcance en v1; las acciones de
  escritura requieren conexión y fallan con aviso, sin cola.
- **Reintentos / idempotencia**: sin clave de idempotencia en v1; un reintento
  puede duplicar, aceptable por RF-17.
- **Lápidas de sincronización** (RF-16): se conservan sin purga automática (no
  hay scheduler, constitución 5); limpieza manual en mantenimiento. Metadato de
  sync sin UI ni endpoint que lo liste ⇒ no es historial navegable (constitución
  7).
- **Manifest** (RF-28): iconos de al menos 192 y 512 px.
- **Primer arranque offline** (RF-29): página offline mínima si nunca hubo carga
  con conexión.
- **Tests de PWA** (RF-28/29 vs constitución 3): tests de contenido del
  manifest/`sw.js` + verificación manual de Lighthouse; no se exige test de
  navegador completo.
- **Control total con el enlace** (Actores, RF-8/9/19): intencional; quien tiene
  el slug puede renombrar, vaciar y eliminar la lista.

### Fase 3 — segunda ronda

- **Formato del slug** (RF-1, RNF "Slug no adivinable"): 16 bytes de un CSPRNG
  codificados en base64url sin relleno (22 caracteres `[A-Za-z0-9_-]`, sensible a
  mayúsculas). Segmento de URL `/l/{slug}`; coincidencia exacta y sensible a
  mayúsculas. Se elige 128 bits exactos y codificación URL-safe corta frente a
  hex (más largo) o `Str::random` (entropía menos explícita).
- **RF-18 vs sincronización** (RF-18, RF-24): RF-18 rige la carga completa, que el
  cliente respeta sin recalcular. El delta de sync no lleva posición, así que tras
  fusionarlo el cliente recoloca los ítems afectados con la misma regla de orden
  (no comprados primero, luego por creación ascendente). Se prefiere duplicar una
  regla trivial y determinista antes que engordar cada respuesta de sync con el
  orden canónico.
- **Andamiaje de autenticación** (RF-30, constitución 4): el esqueleto de auth de
  Laravel (modelo `User`, migraciones `users`/`sessions`/`password_reset_tokens`/
  `personal_access_tokens`, `laravel/sanctum`, ruta `/user`) se elimina antes de
  implementar el resto de la spec, para cumplir al pie de la letra "no hay tabla
  de usuarios". No se reinterpreta la constitución.
- **Contenido como texto plano** (RF-32, nuevo): `name`, `quantity` y `added_by`
  se almacenan y renderizan siempre como texto, nunca como HTML, en servidor y
  cliente. Cierra el hueco de XSS entre dispositivos de una app sin autenticación.
- **Enlace público absoluto** (RF-1): la respuesta de creación devuelve
  `{APP_URL}/l/{slug}`. Se añade a criterios de finalización comprobar `APP_URL`
  en el despliegue.
- **Carga completa sin lápidas** (RF-24): sin cursor válido el servidor devuelve
  solo ítems activos y `deleted_ids` vacío; el cliente parte de cero y no
  necesita saber qué borrar. Acota la respuesta de arranque en frío.
- **Cursor = contador de versión monótono** (RF-24, RNF Sincronización): se
  sustituye el corte por `updated_at` por un contador entero por lista,
  incrementado de forma atómica en cada escritura y usado para sellar filas e
  ítems (incluidas lápidas). El delta es `versión > cursor` (estricto). Elimina
  de raíz el SQL no portable a SQLite, el desfase de timezone BD↔Eloquent y el
  truncado a segundos. El cursor sigue siendo opaco para el cliente.
- **Polling en segundo plano** (RF-22): se pausa con la pestaña oculta (Page
  Visibility) y se reanuda con una consulta inmediata al volver al foco. Menos
  carga en el hosting compartido.
- **Orden de escrituras y renombrado concurrente** (RF-25, RF-7): orden de
  llegada al servidor, serializado por transacción; la regla "última escritura,
  campo por campo" se aplica también al nombre de la lista.
- **"Limpiar comprados" según BD** (RF-19): el servidor evalúa el estado
  "comprado" en el momento de procesar, no según la vista del cliente.
- **Listas recordadas** (RF-6): acción "quitar de mis listas", poda al recibir
  404, refresco del nombre guardado y tope de 20 entradas más recientes.
- **Ficheros de icono PWA** (RF-28, criterios de finalización): el test verifica
  que los PNG 192 y 512 existen y se sirven con `Content-Type: image/png`.
- **Borrado físico de lista** (RF-4, RF-8): eliminar una lista borra físicamente
  su fila y todas las de sus ítems (activos y lápidas). El 404 posterior es
  idéntico byte a byte al de un slug que nunca existió; el aviso "ya no existe"
  lo deduce el cliente que la tenía abierta (RF-27).
- **Tests de la capa de cliente** (criterios de finalización, constitución 3):
  RF-6/21/22/23/26/27 se cubren con tests de navegador Playwright integrados en
  `php artisan test` (Pest 4 browser testing). Es dependencia de desarrollo, no
  de runtime: no afecta al hosting compartido y se justifica en el plan
  (constitución 1). No hace falta enmendar la constitución 3.
- **Purga de lápidas** (RF-16): comando `php artisan items:purge-tombstones
--before=<fecha>` ejecutado a mano en mantenimiento; con test propio, sin
  scheduler. Documentar en `docs/deploy.md`.
- **Campos opcionales vs obligatorios** (RF-2/RF-13 vs RF-11/RF-12): `name` de
  lista e ítem es obligatorio — vacío tras recortar se rechaza con error de
  validación. `quantity` y `added_by` son opcionales — vacío tras recortar
  simplemente no se guarda, sin error. Es intencional.
- **HTTPS obligatorio en producción** (RNF, RF-28/29): el service worker y el
  manifest no funcionan fuera de `https://`; las URLs generadas fuerzan `https` y
  el despliegue se verifica.
- **Páginas de lista no indexables** (RNF): `X-Robots-Tag: noindex, nofollow` en
  `/l/{slug}` y `robots.txt` con `Disallow: /l/`, para que el slug no acabe en un
  buscador.
- **Límite de peticiones por IP** (RNF): 10/hora al crear lista, 120/min en el
  resto de escrituras, 60/min en sincronización; 429 al exceder. Cota defensiva
  para hosting compartido sin penalizar el uso familiar real.
- **Payload parcial garantizado por el cliente** (RF-25): el cliente envía solo
  los campos que cambian en cada edición; test de navegador lo verifica.
- **Casos límite añadidos**: ítem creado y borrado entre dos sync (id
  desconocido, se ignora); el cursor no caduca; la sincronización nunca crea
  ítems (RF-20 solo en el alta); slug alterado al pegar responde 404 sin
  normalización.
- **Frontend Alpine.js** (constitución 1/2, AGENTS.md): se fija Alpine (no
  vanilla) como decisión de spec para no dejar la ambigüedad; versión pineada en
  `package.json`, en el input de Vite. El plan lo justifica en "Decisiones
  técnicas".
- **Idioma y validación** (constitución 8, RNF): `APP_LOCALE=es`,
  `APP_FALLBACK_LOCALE=es`, `lang/es/validation.php` publicado; `messages()` a
  mano solo donde haga falta.
- **`.env.example` a MySQL** (constitución 6): `DB_CONNECTION=mysql` con
  marcadores de posición, `SESSION_DRIVER=cookie`. Entra en la tarea T0 de
  saneamiento.
- **RF-12 / lápidas vs "sin historial"** (constitución 7): `added_by` es un campo
  del ítem vigente, no un log por cambio; las lápidas solo se exponen como `id`
  en `deleted_ids`, nunca con `name`, `added_by` ni marcas de tiempo, y no hay
  endpoint ni UI que las liste. No hay conflicto.
