# Validación — Spec 007

**Date:** 2026-11-15

Fase 7 del flujo SDD, generada por `/sdd:validate`.

## Verificación ejecutada

| Comprobación                  | Resultado                                                         |
| ----------------------------- | ----------------------------------------------------------------- |
| `npm run build`               | PASS                                                              |
| `php artisan test`            | PASS — 281 tests, 1475 assertions                                 |
| `npx playwright test`         | PASS — 166 tests (118 previos + 48 de `tests/e2e/dark-mode.spec.js`) |
| `vendor/bin/pint --test`      | PASS                                                              |
| `vendor/bin/phpstan analyse`  | PASS — sin errores                                                |
| Solo se añadieron tokens `dark:` | PASS — al quitar `dark:` de `home`, `list` y `errors/layout` el Blade coincide con `HEAD`; `layout` solo suma metas, `dark:` y el contenido de su `body`/card |
| `resources/js/`, `app/`, rutas, `manifest.json` | PASS — sin cambios |

## Recorrido por requisito

| RF    | Resumen                                             | Evidencia                                                                                                                                                  | Resultado |
| ----- | --------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- | --------- |
| RF-1  | Superficies oscuras y texto claro con layout        | `dark-mode.spec.js`: `body` resuelve a `#030712` en lista y home; vistas con tokens `dark:` (T5-T10); suite e2e completa                                   | PASS      |
| RF-2  | En claro, estilos idénticos a los previos           | Diff de vistas solo con `dark:` añadido; `dark-mode.spec.js` (regresión en claro: `body` `#f9fafb`, aviso `#fffbeb`); 118 e2e y 281 Pest previos sin cambios | PASS      |
| RF-3  | Cambio de esquema en vivo sin perder la edición     | `dark-mode.spec.js`: `page.emulateMedia` claro↔oscuro con una edición abierta conserva input y valor                                                       | PASS      |
| RF-4  | `color-scheme` en meta y CSS, con y sin layout      | `LayoutTest`, `OfflinePageTest`, `ErrorPagesTest`; `:root { color-scheme }` en `app.css` y en los `<style>` independientes                                 | PASS      |
| RF-5  | Contraste AA en todos los estados                   | axe `wcag2a/aa/21aa` en oscuro, 375 y 1280 px: home vacía y con entradas, 404, 503, offline, 500; lista vacía, con ítems, menú, QR, sugerencias, ediciones | PASS      |
| RF-6  | Los nueve avisos: familia, fondo propio, texto propio | `dark-mode.spec.js` (18 casos): fondo distinto del que tiene detrás, texto propio y axe sin violaciones; clases `-950`/`-200` de la misma familia          | PASS      |
| RF-7  | Hover, sugerencia activa, foco ≥3:1, deshabilitado  | `dark-mode.spec.js`: hover difiere del reposo, sugerencia activa difiere, foco ≥3:1 y ≥2 px, deshabilitado con menos contraste; grep de pares sin huecos   | PASS      |
| RF-8  | Targets de 44 px y orden de Tab sin cambios         | `dark-mode.spec.js`: `boundingBox` de los controles a 375 px y orden de 30 Tab idéntico en claro y oscuro                                                  | PASS      |
| RF-9  | QR oscuro sobre contenedor blanco                   | `dark-mode.spec.js`: `#qr-code` en blanco en oscuro; `list-qr.spec.js` (escaneo de píxeles) sigue pasando; `resources/js/` intacto                         | PASS      |
| RF-10 | `offline` y `500` oscuras, sin build ni scripts     | `OfflinePageTest` y `ErrorPagesTest` (`prefers-color-scheme`, sin `@vite` ni `/build/`); axe en oscuro de ambas a 375 y 1280 px                            | PASS      |
| RF-11 | Reemplazo de la caché anterior                      | `ServiceWorkerTest`: `CACHE_VERSION` `v6`                                                                                                                  | PASS      |
| RF-12 | `theme-color` `#030712` en oscuro, `#2563eb` en claro | `LayoutTest`, `OfflinePageTest`, `ErrorPagesTest`: dos metas con su `media`                                                                              | PASS      |

## Uncovered FRs

Ninguno. Todos los RF tienen verificación automatizada.

## Criterios de finalización

- [x] RF-1 a RF-12 verificados con Pest y Playwright (`colorScheme: 'dark'`, incluido el cambio de esquema con la página abierta).
- [x] axe-core sin violaciones en oscuro a 375 y 1280 px, con transiciones desactivadas, en todos los estados de RF-5, más `offline` y `500`.
- [x] Pasada de teclado en oscuro con foco visible (orden de Tab y foco ≥3:1 en `dark-mode.spec.js`).
- [x] Suite existente en claro sin regresiones y sin tocar `resources/js/`.
- [x] `AGENTS.md`, `docs/ui-guidelines.md`, `docs/roadmap.md` y `CHANGELOG.md` actualizados (más `README.md`).

## Veredicto

**Spec cumplida.** Los 12 requisitos pasan y los cinco criterios de finalización están completos.

## Notes

- Verificación manual pendiente (no es criterio de la spec): arranque de la PWA y escaneo del QR en un celular con tema oscuro.
- Fuera del alcance del tema, detectados al auditar y no corregidos para no cambiar el markup claro: el input de nombre en edición inline no tiene `<label>` (axe `label`, se omite solo esa regla en ese paso del spec) y el botón «Agregar» mide 42 px (sin `min-h-11`; excluido del chequeo de 44 px).
- El RF-2 se verifica por construcción (solo se añaden tokens `dark:`) y por regresión (suite previa más aserciones de estilos computados), no con una comparación píxel a píxel antes/después.
- `docs/schema.md`: `unchanged`; la feature no toca migraciones ni modelos.

## Known limits

- La pantalla de arranque de la PWA instalada usa el `background_color` único del manifest: con el sistema en oscuro muestra un destello claro antes de la app.
- El tema solo sigue al sistema: no hay interruptor claro/oscuro manual ni preferencia guardada.

## Updated files

- `docs/roadmap.md`: «Modo oscuro» pasa de Siguiente a Hecho ✅ y sale del backlog.
- `docs/schema.md`: none (unchanged).
