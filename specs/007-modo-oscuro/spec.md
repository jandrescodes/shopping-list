# Spec 007 — Modo oscuro

**Estado:** aprobada

## Contexto y objetivo

La interfaz tiene un único tema claro. En un celular con el sistema en modo
oscuro, la app abre con fondos blancos que deslumbran, sobre todo de noche en
el supermercado o en la cocina.

Esta feature hace que la interfaz siga la preferencia de esquema de color del
sistema (`prefers-color-scheme`). No hay interruptor manual, ni estado
guardado, ni JavaScript para el tema: se resuelve con CSS, así no hay
parpadeo al cargar y las páginas independientes del build (`offline`, `500`)
pueden seguir al sistema igual que el resto.

No cambia el contrato de la API, el sync por polling ni el modelo de datos.

## Usuarios / actores

Cualquier miembro del hogar que use la app desde un dispositivo con tema
oscuro. No aparecen actores nuevos.

## Historias de usuario

- H1: Como usuario con el sistema en modo oscuro quiero que la app se vea
  oscura para no deslumbrarme.
- H2: Como usuario quiero que todos los avisos, menús y estados se lean bien en
  oscuro, igual que en claro.
- H3: Como usuario quiero que el QR de la lista siga siendo escaneable en
  oscuro.

## Requisitos funcionales (criterios de aceptación en EARS)

Definiciones. **Páginas con layout**: home, lista, 404 y 503. **Páginas
independientes**: `offline` y `500` (sin build ni scripts). **Tema oscuro**:
el sistema prefiere `prefers-color-scheme: dark`.

### Tema según el sistema

- RF-1: WHILE in dark theme, THE SYSTEM shall render the pages with layout with dark surfaces and light text.
- RF-2: WHILE the operating system prefers a light color scheme or has no preference, THE SYSTEM shall keep the computed styles of every visible element identical to those before this feature.
- RF-3: WHEN the system preference changes while a page is open, THE SYSTEM shall update the page theme without reloading and without losing the state of any open edit or panel.
- RF-4: THE SYSTEM shall declare `<meta name="color-scheme" content="light dark">` and the matching CSS `color-scheme` on the pages with layout and on the independent pages, so that native controls (checkbox, scrollbars, autofill) and the page background before the CSS loads follow the active theme.

### Legibilidad y accesibilidad

- RF-5: WHILE in dark theme, THE SYSTEM shall keep WCAG AA contrast (4.5:1 for text, 3:1 for interface components, field borders and focus indicators) in these states: home empty and with entries; list empty and with items (including a purchased item with quantity, price and author, and a very long name); open options menu; name suggestions with an active option; inline editing of name, price, currency and budget; QR shown; every notice of RF-6; 404 and 503.
- RF-6: WHILE in dark theme, each notice — offline (amber), copied (green), plain URL (gray), text export (teal), purchases reset (sky), item deleted with undo (blue), budget exceeded (orange), error (red) and delete confirmation (red) — shall keep its hue family from the light theme, a background perceptibly different from the card, and its own text, so that it never relies on color alone.
- RF-7: WHILE in dark theme, THE SYSTEM shall make hover and active-suggestion states differ from the resting state in background or text color, keep focus indicators at 3:1 against the adjacent surface, and render disabled controls with lower contrast than their enabled counterparts.
- RF-8: THE SYSTEM shall keep the 44 px touch targets and the keyboard focus order unchanged in both themes.

### QR

- RF-9: WHILE in dark theme, THE SYSTEM shall render the QR code with its default dark modules on a white container, never inverted.

### Páginas independientes del build

- RF-10: WHILE in dark theme, THE SYSTEM shall render the offline page and the 500 page with dark colors that meet AA contrast, without loading the build or any script.
- RF-11: WHEN a client that holds the previous shell cache loads the app after this release, THE SYSTEM shall replace that cache with a new version that contains the dark-capable offline page.

### Barra del navegador

- RF-12: WHILE in dark theme, THE SYSTEM shall use `#030712` as `theme-color` on every page, selected with a `media="(prefers-color-scheme: dark)"` meta; in light theme it shall keep `#2563eb`.

## Requisitos no funcionales

- **Sin JS ni almacenamiento**: el tema no usa JavaScript, `localStorage` ni cookies (constitución 1). No se modifica nada de `resources/js/`; el QR conserva sus colores por defecto.
- **Sin dependencias nuevas** (constitución 1): se usa el variante `dark:` de Tailwind 4, que por defecto sigue `prefers-color-scheme`.
- **Compatibilidad con los e2e**: las clases de fondo reservadas de los avisos (`bg-amber-50`, `bg-green-50`, `bg-gray-100`, `bg-sky-50`, `bg-teal-50`, `bg-orange-50`; ver `AGENTS.md`), las que los e2e ya usan como locator (`bg-red-50`, `bg-blue-50`) y los `id` reservados no se renombran ni se quitan; el oscuro solo añade variantes `dark:`. Los chips y avisos que ya comparten clase en claro (`bg-gray-100`, `bg-blue-50`) se distinguen en oscuro por su variante `dark:` propia.
- **Verificación como puerta** (constitución 3): `php artisan test`, `npx playwright test` (suite completa, aunque no se toque JS), Pint y Larastan en verde.
- **Mobile-first** (constitución 10): se verifica a 375 y 1280 px.
- **Medición**: axe-core con las transiciones CSS desactivadas (las filas usan `transition-colors`) y un spec e2e dedicado con `colorScheme: 'dark'`; los colores en claro se comprueban con la suite existente más aserciones de estilos computados.

## Casos límite

- Sin preferencia declarada o navegador sin soporte: se ve el tema claro actual (RF-2).
- Cambio de tema del sistema con la lista abierta: se actualiza sin recargar ni perder una edición en curso (RF-3). El QR abierto no se redibuja: su contenedor es blanco en ambos temas y solo cambia el borde.
- Ítem comprado, cantidad, precio y autor: texto atenuado con contraste AA; el tachado solo afecta al nombre y no es portador de color (RF-5).
- Botón deshabilitado: sigue viéndose deshabilitado, no más visible que el habilitado (RF-7).
- Cliente con la cache anterior: hasta que el service worker se actualice ve el shell claro de antes; RF-11 lo reemplaza en la siguiente carga.
- Pantalla de arranque de la PWA instalada en frío con el sistema en oscuro: usa el `background_color` único del manifest y muestra un destello claro antes de la app oscura. Es una limitación del manifest, se acepta y no tiene RF.
- Build fallido o sin CSS: solo `offline` y `500` conservan tema oscuro, porque llevan su propio `<style>`; el resto queda sin estilos, como hoy.
- `forced-colors` y `prefers-contrast: more`: el navegador impone sus colores; la spec no añade manejo propio y no usa colores que lo impidan.

## Fuera de alcance

- Interruptor manual claro/oscuro/auto y su persistencia.
- Cambiar `manifest.json` (`background_color`, `theme_color`): no admite variantes por esquema. El `theme-color` en tiempo de ejecución sí cambia por meta (RF-12); son mecanismos distintos.
- Rediseñar la paleta, los íconos, el favicon o la pantalla de arranque de la PWA (el cuadrado azul con checklist blanco se ve bien en ambos temas).
- Estilos de impresión.
- Elevar los bordes de campos del tema claro a 3:1: solo el tema oscuro los exige (RF-5); el claro no cambia (RF-2).
- Manejo propio de `forced-colors` y `prefers-contrast`.
- Cambios de API, sync, modelo de datos o rutas.

## Criterios de finalización

- [ ] RF-1 a RF-12 verificados con Pest (`color-scheme` en layout, `offline` y `500`; metas `theme-color` con `media`; `prefers-color-scheme` en `offline` y `500`; `CACHE_VERSION` nueva en `ServiceWorkerTest`) y Playwright (`colorScheme: 'dark'`, incluido el cambio de esquema con la página abierta).
- [ ] axe-core (`wcag2a`, `wcag2aa`, `wcag21aa`) sin violaciones en oscuro a 375 y 1280 px, con transiciones desactivadas, en todos los estados de RF-5, más `offline` y `500`.
- [ ] Pasada de teclado en oscuro con foco visible.
- [ ] Suite existente en claro sin regresiones y sin tocar `resources/js/`.
- [ ] `AGENTS.md` (línea «Un solo tema por ahora»), `docs/ui-guidelines.md`, `docs/roadmap.md` y `CHANGELOG.md` actualizados.

## Dudas abiertas

Ninguna que bloquee el plan.

## Clarificaciones

### Fase 2 — spec inicial

Decisiones tomadas por Claude por delegación del usuario («aplica el dark-mode como recomiendes»), con análisis previo del repo; revisables antes de `/sdd:plan`.

- **Activación**: solo según el sistema, sin interruptor (evita un script bloqueante en `<head>`, que chocaría con la regla de vistas sin `<script>` inline, y mantiene `offline`/`500` consistentes).
- **`manifest.json`**: no se toca; se acepta el destello de arranque.
- **`offline` y `500`**: dentro del alcance, con `@media (prefers-color-scheme: dark)` en su `<style>`.
- **Pruebas**: un spec e2e dedicado (`tests/e2e/dark-mode.spec.js`) con `colorScheme: 'dark'`, no un proyecto Playwright duplicado.
- **Impresión**: fuera de alcance.
- **QR**: se conserva siempre oscuro sobre blanco, nunca invertido.

### Fase 3 — clarify (todas resueltas por criterio de Claude, por delegación del usuario)

- **¿Qué incluye «error pages» en RF-1?** Se definen «páginas con layout» (home, lista, 404, 503) e «independientes» (`offline`, `500`). Toca RF-1, RF-10.
- **¿Cómo se mide «exactly as before»?** Estilos computados idénticos en claro; la suite existente es la regresión. Toca RF-2 y criterios.
- **RF-3 sin estado perdido.** Se incorpora «sin perder una edición en curso» al RF, ya que el caso límite lo exigía. Toca RF-3.
- **¿Cómo se declara el esquema?** `<meta name="color-scheme">` más CSS, también en las independientes. Toca RF-4.
- **¿Qué estados cubre «every page and state»?** Se enumeran en RF-5 (incluye menú, sugerencias, edición inline, QR, avisos, nombre largo, 404/503).
- **Bordes de campos vs. 3:1.** Resuelto: el oscuro los lleva a 3:1; el claro no cambia. Reemplaza la decisión de la fase 2 («solo igualar el estado actual»). Toca RF-5 y Fuera de alcance.
- **«Distinguishable» de los avisos.** Se reemplaza por criterio verificable: misma familia de color que en claro, fondo perceptiblemente distinto de la card y texto propio. Se nombran los nueve avisos. Toca RF-6.
- **Hover/foco/deshabilitado.** Criterio medible por estado. Toca RF-7.
- **Fondo del QR.** «White container» y módulos por defecto; sin tocar JS. Toca RF-9 y NFR.
- **RF-11 reformulado.** Se expresa como comportamiento observable del cliente con cache previa. Toca RF-11.
- **`theme-color` oscuro.** Se fija `#030712` (igual al fondo de la página); claro `#2563eb`. Toca RF-12. Reemplaza la decisión «p. ej.» de la fase 2.
- **Destello de arranque.** Pasa a caso límite definitivo, sin RF. Toca Casos límite.
- **`manifest.json` vs. RF-12.** Se aclara que el manifest no cambia y el meta es otro mecanismo. Toca Fuera de alcance.
- **Avisos que ya comparten clase en claro.** Se distinguen en oscuro con variante `dark:` propia, sin renombrar. Toca NFR.
- **Alto contraste.** `forced-colors` y `prefers-contrast` fuera de alcance. Toca Casos límite y Fuera de alcance.
- **QR abierto al cambiar de tema, cache anterior, build fallido, tachado, transiciones, nombres largos.** Cada uno quedó en Casos límite, RF-5 o NFR.
- **Constitución 2 (valor del `theme-color`) y 3 (Playwright sin tocar JS).** Valor fijado en RF-12; NFR aclara que se corre la suite completa y que no se modifica `resources/js/`.
