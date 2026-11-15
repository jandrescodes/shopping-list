# Tasks — Spec 007

Tareas de <30 min, ordenadas por dependencia. Cada una: sus RF, un checkbox y
una línea "Done when:" verificable. Derivadas de `plan.md`; los RF salen de
`spec.md`.

Puerta de verificación por tarea (`AGENTS.md`): `php artisan test` y
`vendor/bin/pint` sobre lo tocado. Las tareas que cambian vistas (T5-T10)
corren además `npm run build` y `npx playwright test` completo, porque los
locators e2e dependen de clases literales.

Reglas que atraviesan todo el bloque:

- **Solo se añaden tokens `dark:`**: no se renombra ni se quita ninguna clase
  clara ni `id` reservado; el estilo en claro no cambia (RF-2).
- **No se toca `resources/js/`, `app/`, rutas ni `manifest.json`**.
- Una clase de oscuro sin prefijo nunca reutiliza un fondo reservado de aviso.
- Cada `hover:`/`disabled:` con color lleva su par `dark:`.
- El contenedor `#qr-code` no recibe variante de fondo (solo de borde).

## Base

- [x] T0. Baseline: ejecutar `php artisan test`, `npx playwright test`,
      `vendor/bin/pint --test` y `vendor/bin/phpstan analyse` sin cambios.
      Done when: pasan y los conteos quedan registrados en esta tarea.
      Conteos: Pest 281 pasan (1462 aserciones); Playwright 118 pasan;
      Pint y Larastan sin errores.

## Esquema declarado y shell

- [x] T1. `app.css`: `:root { color-scheme: light dark; }`; `layout.blade.php`:
      `<meta name="color-scheme" content="light dark">` y dos metas
      `theme-color` con `media` (`#2563eb` claro, `#030712` oscuro); ampliar
      `tests/Feature/LayoutTest.php`. (RF-4, RF-12)
      Done when: `LayoutTest` comprueba el meta `color-scheme` y las dos metas
      `theme-color` con su `media`, y pasa.

- [x] T2. `offline.blade.php`: meta `color-scheme`, dos metas `theme-color`,
      `:root { color-scheme: light dark; }` y `@media (prefers-color-scheme:
dark)` con body `#030712`/`#f3f4f6` y `p` `#9ca3af`; ampliar
      `OfflinePageTest`. (RF-4, RF-10, RF-12)
      Done when: `OfflinePageTest` verifica meta, `prefers-color-scheme: dark`
      en el `<style>` y que sigue sin `@vite` ni registro del SW, y pasa.

- [x] T3. `errors/500.blade.php`: lo mismo que T2 más `.code` `#60a5fa`;
      ampliar `ErrorPagesTest`. (RF-4, RF-10, RF-12)
      Done when: `ErrorPagesTest` verifica meta, `prefers-color-scheme: dark`
      y que el 500 sigue sin `/build/`, y pasa.

- [x] T4. `public/sw.js`: `CACHE_VERSION` de `v5` a `v6` y su comentario;
      actualizar `ServiceWorkerTest`. (RF-11)
      Done when: `ServiceWorkerTest` espera `const CACHE_VERSION = 'v6';` y
      pasa.

## Vistas con layout

- [x] T5. `layout.blade.php`: variantes `dark:` de body, barra de marca, card
      desktop (`dark:md:`) y enlace «Mis listas». (RF-1, RF-5)
      Done when: `npm run build`, `php artisan test` y `npx playwright test`
      en verde, y `body` resuelve a `#030712` con `colorScheme: 'dark'`
      (comprobación puntual con Playwright).

- [x] T6. `home.blade.php`: variantes `dark:` de textos, campo, botón, estado
      vacío, ícono decorativo, lista «Mis listas» y error (bordes de campo
      `dark:border-gray-500`). (RF-1, RF-5, RF-7)
      Done when: build, `php artisan test` y `npx playwright test` en verde, y
      una captura en oscuro a 375 px de home vacía y con entradas queda
      adjunta como evidencia.

- [x] T7. `errors/layout.blade.php` (404 y 503): variantes `dark:` de código,
      encabezado, mensaje y botón «Reintentar». (RF-1, RF-5)
      Done when: build, `php artisan test` y `npx playwright test` en verde.

- [x] T8. `list.blade.php` (cabecera y avisos): variantes `dark:` de título,
      menú de opciones, hovers, avisos error, offline, copiado, URL en claro,
      exportar, reinicio y deshacer, y confirmación de borrado. Cada aviso
      conserva su clase clara y añade `dark:bg-{color}-950` y
      `dark:text-{color}-200`. (RF-1, RF-5, RF-6, RF-7)
      Done when: build, `php artisan test` y `npx playwright test` en verde.

- [x] T9. `list.blade.php` (formulario y paneles): variantes `dark:` del campo
      de nombre, sugerencias (incluida la opción activa en la cadena Alpine),
      edición de nombre/moneda/presupuesto, aviso de presupuesto y borde del
      contenedor del QR (sin fondo). (RF-1, RF-5, RF-6, RF-7, RF-9)
      Done when: build, `php artisan test` y `npx playwright test` en verde;
      `list-qr.spec.js` sigue pasando sin cambios.

- [x] T10. `list.blade.php` (filas y cierre): variantes `dark:` de la fila del
      ítem (comprado, cantidad, precio, autor, botones subir/bajar con su
      `disabled:`), estado vacío, panel de limpieza y botón «Sí, limpiar»
      (`dark:bg-gray-600`). (RF-1, RF-5, RF-7)
      Done when: build, `php artisan test` y `npx playwright test` en verde y
      un grep de `hover:` y `disabled:` con color en `list.blade.php` no deja
      ninguno sin su par `dark:`.

## Pruebas e2e en oscuro

- [x] T11. `tests/e2e/dark-mode.spec.js` (base): `colorScheme: 'dark'`; estilos
      computados de `body`, `#qr-code` y un aviso; caso en claro como
      regresión; cambio de esquema en vivo con `page.emulateMedia` conservando
      una edición abierta. (RF-1, RF-2, RF-3, RF-6, RF-9)
      Done when: el spec pasa con `npx playwright test tests/e2e/dark-mode.spec.js`.

- [x] T12. Axe en oscuro de las páginas sin lista: home vacía y con entradas,
      404, 503, `/offline` y 500, a 375 y 1280 px, con transiciones
      desactivadas y etiquetas `wcag2a`, `wcag2aa`, `wcag21aa`. Ajustar tonos
      si hay violaciones. (RF-5, RF-10)
      Done when: el spec de T11 incluye esos casos y pasa con cero
      violaciones.

- [x] T13. Axe en oscuro de la lista: vacía, con ítems (comprado con cantidad,
      precio, autor y un nombre muy largo), menú abierto, sugerencias con una
      opción activa, edición inline de nombre, precio, moneda y presupuesto, y
      QR mostrado. (RF-5, RF-9)
      Done when: esos casos pasan con cero violaciones y el escaneo de píxeles
      del QR en `list-qr.spec.js` sigue pasando.

- [x] T14. Axe en oscuro de cada aviso: offline (amber), copiado (green), URL
      en claro (gray), exportar (teal), reinicio (sky), deshacer (blue),
      presupuesto (orange), error (red) y confirmación de borrado. Comprobar
      fondo distinto de la card y texto propio. (RF-5, RF-6)
      Done when: el spec cubre los nueve avisos y pasa con cero violaciones.

- [x] T15. Estados y teclado en oscuro: hover y sugerencia activa difieren del
      reposo, botón deshabilitado con menos contraste que el habilitado, foco
      visible ≥3:1, targets de 44 px (`boundingBox`) y orden de Tab sin
      cambios respecto al claro. (RF-7, RF-8)
      Done when: esas aserciones pasan en el spec e2e.

## Documentación

- [x] T16. `AGENTS.md`: reemplazar «Un solo tema por ahora» por el criterio de
      tema según el sistema, la regla de solo añadir `dark:` a clases
      reservadas y la de `text-gray-500` en oscuro; `docs/ui-guidelines.md`:
      sección con el mapeo por rol, el QR sobre blanco y axe también en oscuro.
      (criterios de finalización)
      Done when: `grep -n "Un solo tema" AGENTS.md` no devuelve nada y la
      sección nueva existe en `docs/ui-guidelines.md`.

## Cierre de la feature

- [x] Suite completa: `php artisan test`, `npx playwright test`,
      `vendor/bin/pint --test` y `vendor/bin/phpstan analyse` pasan sin
      cambios pendientes.
      Resultado: Pest 281 (1475 aserciones); Playwright 166 (118 previos +
      48 de `dark-mode.spec.js`); Pint y Larastan sin errores.
- [x] Validación SDD: ejecutar `/sdd:validate` para generar `validation.md`,
      recorrer RF-1 a RF-12 y emitir el veredicto.
- [x] `CHANGELOG.md`: nueva entrada con el modo oscuro, `CACHE_VERSION` `v6`
      y la limitación del arranque de la PWA.
- [x] `docs/roadmap.md`: mover «Modo oscuro» del backlog a Hecho ✅ (lo hace
      `/sdd:validate`).
- [x] Verificación manual en un celular con tema oscuro: arranque de la PWA y
      escaneo del QR. <blank>

## Cobertura RF → tarea

| RF    | Tareas                                   |
| ----- | ---------------------------------------- |
| RF-1  | T5, T6, T7, T8, T9, T10, T11             |
| RF-2  | T11 (regresión en claro) y toda la suite |
| RF-3  | T11                                      |
| RF-4  | T1, T2, T3                               |
| RF-5  | T5, T6, T7, T8, T9, T10, T12, T13, T14   |
| RF-6  | T8, T9, T11, T14                         |
| RF-7  | T6, T8, T9, T10, T15                     |
| RF-8  | T15                                      |
| RF-9  | T9, T11, T13                             |
| RF-10 | T2, T3, T12                              |
| RF-11 | T4                                       |
| RF-12 | T1, T2, T3                               |
