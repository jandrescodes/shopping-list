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
  Moneda, Presupuesto, Copiar como texto, Duplicar lista, Reiniciar compras (solo con
  ítems comprados, `x-show="hasPurchased"`) y Eliminar lista; los avisos de
  esas acciones llevan su propio `id` y fondo (`#export-notice`,
  `#reset-undo`, `#budget-alert`), ver `AGENTS.md`. El formulario inline de
  Presupuesto (`#budget-input`) sigue el patrón de la moneda y su campo mide
  `min-h-11`.
- **Reordenar.** El asa (`x-sort:handle`) lleva `touch-none` solo ella: el
  resto de la fila deja pasar el scroll vertical del celular. Los botones
  subir/bajar van en una segunda línea de la fila (`col-start-4 row-start-2`),
  son la alternativa de teclado y lector de pantalla, y las posiciones se
  anuncian en `#move-announcement` (`role="status"`). Sin dos pendientes no hay
  controles.
- **Sugerencias del nombre.** `#new-item` es un combobox (`aria-expanded`,
  `aria-activedescendant`) y el panel `#name-suggestions` es un `listbox` con
  opciones `min-h-11` que se eligen con `mousedown.prevent` para no cerrar el
  teclado del celular; Enter con una sugerencia activa solo rellena el nombre.
- **Aviso de presupuesto.** `#budget-alert` usa `bg-orange-50` con texto
  `text-orange-900` y el importe escrito («Te pasas por Bs 5,00»): no depende
  solo del color y no reutiliza los fondos reservados.
- **Compartir y QR.** El botón `#show-qr` se mantiene junto a «Compartir
  enlace», alterna «Mostrar QR»/«Ocultar QR» y expone `aria-expanded`. El
  contenedor `#qr-code` centra el canvas, no repite la URL debajo y usa un
  nombre accesible para el código. Ambos controles conservan targets mínimos
  de 44 px y deben funcionar con teclado.
- **Un solo aviso de conexión.** `showOfflineNotice` (`list.js`) oculta el
  aviso ámbar mientras el error visible es el de escritura sin red, que ya
  dice "Sin conexión"; así no se apilan dos avisos casi iguales en mobile.
- **Clases canónicas de Tailwind**: `mt-4.5`, no `mt-[18px]`
  (tailwindcss-intellisense lo marca).
- **Verificación visual y de accesibilidad.** axe-core (`wcag2a`, `wcag2aa`,
  `wcag21aa`) a 375 y 1280px, con las transiciones CSS desactivadas, más una
  pasada de teclado (orden de Tab, foco visible, foco tras cada acción). La
  integración e2e usa `@axe-core/playwright` como dependencia de desarrollo
  pineada. Crear listas de prueba choca con
  `lists-create` (10 por hora por IP en dev): reutiliza una existente en modo
  lectura y borra las tuyas por su marker.
- **Formateador del IDE.** Debe respetar Pint: uno distinto reescribió
  `fn ($x)` a `fn($x)` en los tests y rompió la indentación de este archivo.

## Modo oscuro

El tema sigue al sistema con `prefers-color-scheme`; no hay interruptor ni
preferencia guardada. Tailwind 4 aplica `dark:` por media query, así que el
cambio de esquema con la página abierta se refleja sin recargar ni perder una
edición en curso. El estilo claro no cambia: solo se añaden tokens `dark:`.

- **Declaración.** `:root { color-scheme: light dark; }` en `app.css` y el
  meta `color-scheme` en `layout`, `offline` y `errors/500`; dos metas
  `theme-color` con `media` (claro `#2563eb`, oscuro `#030712`). El
  `manifest.json` no se toca.
- **Mapeo por rol.**

    | Rol                               | Clase en oscuro                                                      |
    | --------------------------------- | -------------------------------------------------------------------- |
    | Fondo de página                   | `dark:bg-gray-950`                                                   |
    | Card desktop                      | `dark:md:bg-gray-900 dark:md:ring-gray-800`                          |
    | Texto principal / secundario      | `dark:text-gray-100` (200, 300) / `dark:text-gray-400`               |
    | Ícono decorativo                  | `dark:text-gray-500`                                                 |
    | Campo                             | `dark:bg-gray-900 dark:text-gray-100 dark:placeholder:text-gray-400` |
    | Borde de campo                    | `dark:border-gray-500` (≥3:1)                                        |
    | Separadores                       | `dark:border-gray-700` / `dark:divide-gray-700`                      |
    | Superficie flotante (menú, panel) | `dark:bg-gray-800 dark:border-gray-700`                              |
    | Hover                             | `dark:hover:bg-gray-700`                                             |
    | Enlaces                           | `dark:text-blue-400` (hover `-300`)                                  |
    | Avisos                            | `dark:bg-{color}-950 dark:text-{color}-200`                          |
    | Botón primario / destructivo      | sin cambio                                                           |
    | «Sí, limpiar»                     | `dark:bg-gray-600`                                                   |
    | Deshabilitado                     | `dark:disabled:text-gray-600`                                        |

- **Pares.** Cada `hover:` o `disabled:` con color lleva su par `dark:` en el
  mismo elemento (también `dark:hover:` y `dark:disabled:hover:`).
- **QR sobre blanco.** `#qr-code` conserva el fondo blanco para que el código
  se escanee; en oscuro solo cambia su borde.
- **Páginas independientes.** `offline` y `errors/500` llevan su propio
  `<style>` con hex y `@media (prefers-color-scheme: dark)`, sin build ni
  scripts. Cambiar `offline` exige subir `CACHE_VERSION` de `sw.js`.
- **Limitación conocida.** La pantalla de arranque de la PWA instalada usa el
  `background_color` único del manifest: con el sistema en oscuro hay un
  destello claro antes de la app.
- **Verificación.** axe-core también en oscuro (375 y 1280px, transiciones
  desactivadas), en cada estado y en los nueve avisos; hover, sugerencia
  activa, deshabilitado, foco ≥3:1, targets de 44px y orden de Tab igual al
  claro. Todo en `tests/e2e/dark-mode.spec.js`.
- **Pendientes ajenos al tema** detectados al auditar: el input de nombre en
  edición inline no tiene `<label>` y «Agregar» mide 42px (sin `min-h-11`).
