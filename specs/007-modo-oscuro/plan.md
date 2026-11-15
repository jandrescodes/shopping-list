# Plan técnico — Spec 007

## Estado de partida del repo

Release 1.6.1 (spec 006 cerrada). Baseline esperada: 281 Pest y 118
Playwright en verde (T0 registra los conteos reales). Contexto que condiciona
el plan:

- No existe nada de `dark:`, `color-scheme` ni `prefers-color-scheme` en
  `resources/`, `tests/` ni `docs/`.
- Tailwind 4.3.5: el variante `dark:` usa `@media (prefers-color-scheme: dark)`
  por defecto, así que `resources/css/app.css` no necesita `@custom-variant`.
- Las clases de color viven en las vistas Blade (`layout`, `home`, `list`,
  `errors/layout`); `resources/js/` no contiene clases de color salvo dos
  cadenas dinámicas en atributos Alpine de `list.blade.php` (sugerencia activa
  y texto del comprado).
- `offline.blade.php` y `errors/500.blade.php` son standalone con `<style>` y
  hex fijos; no pueden usar el build.
- `public/sw.js` tiene `CACHE_VERSION = 'v5'`; `/offline`, `/` y
  `/manifest.json` están en `STATIC_SHELL` y la rama cache-first no revalida.
  `ServiceWorkerTest` fija el literal `'v5'`.
- El QR (`renderQr` en `list.js`) usa los colores por defecto de la librería
  dentro de `#qr-code` (`bg-white p-3`).
- Los locators e2e dependen de clases literales (`.bg-amber-50`, `.bg-green-50`,
  `.bg-gray-100`, `p.bg-red-50`, `.bg-blue-50`, `/bg-orange-50/`) y de `id`
  reservados; el oscuro solo puede añadir tokens `dark:`.
- Solo `tests/e2e/list-qr.spec.js` usa axe y acotado al QR; hay un único
  proyecto Playwright (`chromium`) sin `colorScheme`.

## Estructura de módulos

- `resources/views/layout.blade.php` → `<meta name="color-scheme">`, dos metas
  `theme-color` con `media`, y las clases `dark:` de body, barra de marca,
  card desktop y enlace «Mis listas» (RF-1, RF-4, RF-12).
- `resources/css/app.css` → `:root { color-scheme: light dark; }` (RF-4).
- `resources/views/home.blade.php`, `resources/views/list.blade.php`,
  `resources/views/errors/layout.blade.php` → clases `dark:` por rol según el
  mapeo de abajo (RF-1, RF-5, RF-6, RF-7, RF-9).
- `resources/views/offline.blade.php`, `resources/views/errors/500.blade.php`
  → `color-scheme`, metas de tema y bloque `@media (prefers-color-scheme: dark)`
  en su `<style>` (RF-4, RF-10, RF-12).
- `public/sw.js` → `CACHE_VERSION` de `v5` a `v6` y su comentario (RF-11).
- `tests/Feature/ServiceWorkerTest.php`, `LayoutTest.php`,
  `OfflinePageTest.php`, `ErrorPagesTest.php` → aserciones nuevas o
  actualizadas (RF-4, RF-10, RF-11, RF-12).
- `tests/e2e/dark-mode.spec.js` (nuevo) → emulación `colorScheme: 'dark'`,
  estilos computados, cambio de esquema en vivo y auditoría axe (RF-1..RF-9).
- Docs: `AGENTS.md`, `docs/ui-guidelines.md`, `docs/roadmap.md`,
  `CHANGELOG.md` (criterios de finalización).

Sin módulo de `resources/js/` ni de `app/`: no hay lógica de negocio nueva.

## Modelo de datos

Stateless. Sin migraciones, rutas, contratos de API ni almacenamiento del
cliente.

## Algoritmos no triviales

### Mapeo claro → oscuro por rol (RF-1, RF-5, RF-6, RF-7)

Regla: el estilo claro no se toca; cada elemento recibe además su variante
`dark:`. Fondos de aviso con 950 sólido (no con opacidad) para que axe mida un
color determinista. Los contrastes se confirman con axe: Tailwind 4 usa oklch y
las razones aproximadas con hex de la v3 pueden variar.

| Rol                                                          | Claro (actual)                                                 | Oscuro (añade)                                                           |
| ------------------------------------------------------------ | -------------------------------------------------------------- | ------------------------------------------------------------------------ |
| Fondo de página                                              | `bg-gray-50`                                                   | `dark:bg-gray-950`                                                       |
| Card desktop                                                 | `md:bg-white md:ring-gray-200`                                 | `dark:md:bg-gray-900 dark:md:ring-gray-800`                              |
| Texto base / fuerte                                          | `text-gray-900/800/700`                                        | `dark:text-gray-100/200/300`                                             |
| Texto secundario                                             | `text-gray-600`, `text-gray-500`                               | `dark:text-gray-400` (nunca `gray-500` sobre `gray-900`: ~3.9:1)         |
| Ícono decorativo                                             | `text-gray-400`                                                | `dark:text-gray-500`                                                     |
| Bordes de campo                                              | `border-gray-300`                                              | `dark:border-gray-500` (≥3:1, RF-5)                                      |
| Separadores, contornos                                       | `border/divide-gray-200`                                       | `dark:border/divide-gray-700`                                            |
| Superficies flotantes (menú, sugerencias, panel de limpieza) | `bg-white`/`bg-gray-50` + borde                                | `dark:bg-gray-800 dark:border-gray-700`                                  |
| Hover de superficie                                          | `hover:bg-gray-50/100`                                         | `dark:hover:bg-gray-700`                                                 |
| Chips de cantidad y URL en claro                             | `bg-gray-100 text-gray-600`                                    | `dark:bg-gray-800 dark:text-gray-300`                                    |
| Enlaces y acciones azules                                    | `text-blue-700`, `hover:text-blue-700`                         | `dark:text-blue-400`, `dark:hover:text-blue-300`                         |
| Sugerencia activa                                            | `bg-blue-50 text-blue-800`                                     | `dark:bg-blue-950 dark:text-blue-200`                                    |
| Primario / destructivo                                       | `bg-blue-600`, `bg-red-600` + `text-white`                     | sin cambio (confirmar con axe)                                           |
| Botón «Sí, limpiar»                                          | `bg-gray-800 text-white`                                       | `dark:bg-gray-600`                                                       |
| Deshabilitado                                                | `disabled:text-gray-300`                                       | `dark:disabled:text-gray-600`                                            |
| Foco                                                         | `outline-blue-500`                                             | sin cambio (≥3:1); `dark:outline-blue-400` si axe lo pide                |
| Checkbox                                                     | `accent-blue-600`                                              | sin cambio; `color-scheme` oscurece el control nativo                    |
| Avisos                                                       | `bg-{amber,green,teal,sky,blue,orange,red}-50` + texto 700–900 | `dark:bg-{color}-950` + `dark:text-{color}-200` (botón del aviso `-300`) |

Casos límite: una clase de oscuro sin prefijo nunca reutiliza un fondo reservado
(p. ej. un chip no pasa a `bg-gray-100` a secas); los `hover:` y `disabled:` que
faltaran pintarían blanco sobre oscuro, así que cada uno lleva su par; el
contenedor del QR no recibe variante de fondo.

### Esquema de color declarado (RF-4, RF-10, RF-12)

1. `<meta name="color-scheme" content="light dark">` en `layout`, `offline` y
   `500`.
2. `:root { color-scheme: light dark; }` en `app.css` y en el `<style>` de las
   dos standalone.
3. Dos `<meta name="theme-color">`: `#2563eb` con
   `media="(prefers-color-scheme: light)"` y `#030712` con
   `media="(prefers-color-scheme: dark)"`, en las tres vistas con `<head>`.
4. Standalone: `@media (prefers-color-scheme: dark)` con body `#030712`/`#f3f4f6`,
   `p` `#9ca3af`, `.code` `#60a5fa` (solo `500`); el enlace-botón `#1d4ed8` con
   texto blanco queda igual (≈6.7:1).

### Invalidación del shell (RF-11)

`CACHE_VERSION = 'v6'`: `activate` descarta cachés de versiones anteriores y el
precache vuelve a bajar `/`, `/offline` y el manifiesto. Un cliente con la cache
`v5` sigue viendo el shell claro hasta la siguiente carga con red (caso límite
de la spec).

## Decisiones técnicas

| #   | Decisión                                                                       | Alternativa descartada y por qué                                                                                                                                                                 |
| --- | ------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| D1  | Variante `dark:` por defecto (media query), sin `@custom-variant`.             | Modo por clase o atributo: exige JS o script bloqueante en `<head>`, choca con «sin `<script>` inline» y con la spec (solo sistema).                                                             |
| D2  | Duplicar clases `dark:` en las vistas.                                         | Tokens semánticos en `@theme` (`--color-surface`…): los locators e2e dependen del nombre literal `bg-amber-50` y compañía, y reescribir todas las vistas rompe la regla «solo añadir variantes». |
| D3  | Fondos de aviso `-950` sólidos.                                                | `-900/30` con opacidad: el color efectivo depende de la superficie y axe lo mide peor.                                                                                                           |
| D4  | `color-scheme` por meta **y** CSS.                                             | Solo CSS: el primer pintado antes de cargar el stylesheet sería blanco; solo meta: no cubre elementos si el CSS lo contradice.                                                                   |
| D5  | QR siempre módulos oscuros sobre contenedor blanco; sin tocar JS.              | Invertir el QR: muchos lectores no leen códigos invertidos.                                                                                                                                      |
| D6  | Dos metas `theme-color` con `media`; `manifest.json` intacto.                  | Cambiar `background_color` del manifest: no admite variantes y afectaría a quien usa claro.                                                                                                      |
| D7  | Standalone con hex duplicados en `@media`.                                     | Cargar el build: rompe el requisito de que rendericen sin build ni red.                                                                                                                          |
| D8  | Spec e2e dedicado `dark-mode.spec.js` con `test.use({ colorScheme: 'dark' })`. | Segundo proyecto Playwright en oscuro: duplica las ~118 pruebas y el tiempo de CI sin ganancia sobre un spec de estados clave.                                                                   |
| D9  | Bordes de campo a `gray-500` en oscuro (≥3:1) y claro sin cambios.             | Subir también el claro: viola RF-2 (estilos computados idénticos).                                                                                                                               |
| D10 | Subir `CACHE_VERSION` a `v6`.                                                  | No subirla: los clientes conservarían el `/offline` claro cacheado y RF-11 no se cumpliría.                                                                                                      |

## Contrato de interfaz

Sin API ni rutas nuevas. Contrato observable:

| Elemento                                          | Interacción                                                                                    |
| ------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `<meta name="color-scheme" content="light dark">` | Presente en `/`, `/l/{slug}`, 404, 503, `/offline` y 500.                                      |
| `<meta name="theme-color" media="…">`             | Dos metas por página con `<head>` (claro `#2563eb`, oscuro `#030712`).                         |
| Estilos computados en oscuro                      | `body` fondo `#030712` y texto claro; `#qr-code` fondo blanco; chips y avisos con su variante. |
| `/sw.js`                                          | `CACHE_VERSION = 'v6'`.                                                                        |

## Riesgos

| Riesgo                                                                       | Mitigación / por qué se acepta                                                                                       |
| ---------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------- |
| Contraste real distinto del estimado (oklch vs hex v3).                      | Axe en oscuro por cada estado de RF-5; se ajusta el tono (`-200`/`-300`, `-950`/`-900`) hasta cero violaciones.      |
| Un `hover:`/`disabled:` sin par oscuro deja un fondo blanco.                 | Revisión por grep de `hover:bg-`, `hover:text-`, `disabled:` y aserción e2e del hover y del deshabilitado en oscuro. |
| Axe lee colores a medio animar (`transition-colors`).                        | Inyectar `transition:none` antes de auditar (lección del repo).                                                      |
| Reutilizar clases reservadas rompe locators.                                 | NFR de la spec; la suite e2e completa corre en cada tarea.                                                           |
| Estado heredado entre pruebas (sidebar, `localStorage`) da falsos positivos. | El tema se activa con `colorScheme` del contexto de Playwright, sin tocar clases a mano; contexto nuevo por test.    |
| Cliente con cache `v5` ve claro un tiempo.                                   | Aceptado: se resuelve en la siguiente carga (RF-11).                                                                 |
| Destello claro al arrancar la PWA instalada.                                 | Aceptado: limitación del manifest (spec, casos límite).                                                              |
| Tonos de las standalone difieren algo de la app (hex v3 vs oklch).           | Aceptado: no se mide entre sí; ambas cumplen AA.                                                                     |

## Estrategia de verificación

- **Pest**:
    - `LayoutTest`: `color-scheme` y las dos metas `theme-color` con `media` (RF-4, RF-12).
    - `OfflinePageTest` y `ErrorPagesTest`: `color-scheme`, `prefers-color-scheme: dark` en el `<style>`, sin `@vite` (RF-4, RF-10, RF-12).
    - `ServiceWorkerTest`: literal `v6` (RF-11).
- **Playwright** (`tests/e2e/dark-mode.spec.js`, `colorScheme: 'dark'`):
    - estilos computados de `body`, `#qr-code` y un aviso (RF-1, RF-6, RF-9);
    - cambio de esquema en vivo con `page.emulateMedia` y una edición abierta que no se pierde (RF-3);
    - un caso en claro como regresión (RF-2);
    - axe `wcag2a`/`wcag2aa`/`wcag21aa` a 375 y 1280 px con transiciones desactivadas, en todos los estados de RF-5 y en `offline`, `500`, 404 y 503 (RF-5, RF-10);
    - tamaño 44 px y foco visible en oscuro (RF-7, RF-8).
- **Suite completa**: `php artisan test`, `npx playwright test`,
  `vendor/bin/pint`, `vendor/bin/phpstan analyse` (constitución 3).
- **Manual (no automatizable)**: revisar en un celular con tema oscuro del
  sistema el arranque de la PWA y el escaneo del QR; se anota en la validación.

## Cobertura de RF

| RF    | Parte del plan que lo cubre                                                    |
| ----- | ------------------------------------------------------------------------------ |
| RF-1  | Mapeo por rol (vistas Blade); e2e de estilos computados                        |
| RF-2  | Solo se añaden tokens `dark:`; D9; e2e de regresión en claro y suite existente |
| RF-3  | Variante por media query (D1); e2e con `emulateMedia`                          |
| RF-4  | Esquema declarado (meta + CSS, D4); Pest de layout/offline/500                 |
| RF-5  | Mapeo por rol, bordes D9; axe por estado                                       |
| RF-6  | Fondos de aviso 950 + texto 200 (D3); e2e de estilos y axe                     |
| RF-7  | Pares `hover:`/`disabled:` oscuros; e2e de hover/deshabilitado                 |
| RF-8  | Sin cambios de tamaño ni orden; e2e de 44 px y teclado                         |
| RF-9  | QR sin variante de fondo (D5); e2e de estilo y de píxeles existente            |
| RF-10 | Standalone con `@media` (D7); Pest + axe                                       |
| RF-11 | `CACHE_VERSION = 'v6'` (D10); `ServiceWorkerTest`                              |
| RF-12 | Dos metas `theme-color` (D6); `LayoutTest` y standalone                        |
