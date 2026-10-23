# Guía de UI y Alpine

Detalle de las convenciones de interfaz que `AGENTS.md` resume en una regla.

## Orden de módulos y `Alpine.start()`

**Gotcha de orden**: `list.js` es la entrada global que llama
`Alpine.start()`; si se añade una entrada de página (como `home.js`) que
registra sus propios `Alpine.data()` en `alpine:init`, esa entrada se
imprime _después_ de `list.js` en el HTML (va en `@yield('scripts')`, al
final de `<body>`), así que si `Alpine.start()` corriera de forma síncrona
al final de `list.js` dispararía `alpine:init` antes de que el módulo de
la página hubiera corrido, dejando sus componentes sin definir
(`ReferenceError` en consola). Por eso `Alpine.start()` va envuelto en un
listener de `DOMContentLoaded` (dispara después de que todos los `<script
type="module">` — deferred por naturaleza — ya corrieron y registraron sus
componentes).

## Lecciones de UI

- **Targets táctiles de 44px reales.** Los controles interactivos llevan
  `min-h-11` (`inline-flex items-center`), no un pseudo-elemento `before:` de
  44px: estos se solapan con los vecinos y provocan toques en el control
  equivocado. El pseudo-elemento solo vale para un chip cuya fila ya mide
  ≥44px, acotado a esa fila (`item-quantity`).
- **Texto atenuado.** Nunca `text-gray-400` sobre blanco (~2.5:1); mínimo
  `text-gray-500`, y `text-gray-600` para texto de 12px sobre fondo no blanco.
  Un ítem comprado se marca con el checkbox y `line-through` **solo en el
  nombre**: precio, cantidad y autor quedan atenuados pero legibles.
- **Fila de ítem.** Grid `grid-cols-[2rem_minmax(0,1fr)_auto_2rem]`: checkbox,
  nombre, precio a la derecha (`tabular-nums`) y `×`; cantidad y autor en una
  segunda línea que solo existe si hay alguno. La lista previa a la
  hidratación comparte las mismas columnas para que no salte al arrancar
  Alpine. El nombre debe seguir siendo el primer `span` del `<li>` (los e2e lo
  localizan así) y el tachado va en ese `span`, no en el `<li>`.
- **Menú de opciones del encabezado** (`#list-menu-button` / `#list-menu`):
  patrón disclosure (`aria-expanded`), sin `role="menu"`, que exigiría
  navegación con flechas. `#currency-chip` vive **dentro** del menú cerrado: un
  e2e debe abrirlo antes (`click('#list-menu-button')`). Una acción del menú
  mueve el foco a propósito (`$nextTick(() => $refs.x.focus())`) y lo devuelve
  al cancelar o guardar; si no, cae al `<body>`. Un panel `role="alertdialog"`
  enfoca su botón seguro (Cancelar) al abrirse. Orden del menú: Renombrar,
  Moneda, Copiar como texto, Duplicar lista, Reiniciar compras (solo con
  ítems comprados, `x-show="hasPurchased"`) y Eliminar lista; los avisos de
  esas acciones llevan su propio `id` y fondo (`#export-notice`,
  `#reset-undo`), ver `AGENTS.md`.
- **Un solo aviso de conexión.** `showOfflineNotice` (`list.js`) oculta el
  aviso ámbar mientras el error visible es el de escritura sin red, que ya
  dice "Sin conexión"; así no se apilan dos avisos casi iguales en mobile.
- **Clases canónicas de Tailwind**: `mt-4.5`, no `mt-[18px]`
  (tailwindcss-intellisense lo marca).
- **Verificación visual y de accesibilidad.** axe-core (`wcag2a`, `wcag2aa`,
  `wcag21aa`) a 375 y 1280px, con las transiciones CSS desactivadas, más una
  pasada de teclado (orden de Tab, foco visible, foco tras cada acción). Instala
  axe fuera del proyecto: no es dependencia. Crear listas de prueba choca con
  `lists-create` (10 por hora por IP en dev): reutiliza una existente en modo
  lectura y borra las tuyas por su marker.
- **Formateador del IDE.** Debe respetar Pint: uno distinto reescribió
  `fn ($x)` a `fn($x)` en los tests y rompió la indentación de este archivo.
