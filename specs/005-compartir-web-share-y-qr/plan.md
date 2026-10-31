# Plan técnico — Spec 005

## Estado de partida del repo

Release 1.4.0 (spec 004) cerrada. Baseline esperada tras T0: los conteos de la spec 004 (267 Pest, 112 Playwright) pasan en verde.

Hallazgos del código que condicionan el plan (leídos, no supuestos):

- `list.js:239-265` ya tiene `shareList()`: `navigator.share` con título y URL, fallback a `navigator.clipboard.writeText` + `shareNotice` (`.bg-green-50`), y `shareUrl` (`.bg-gray-100`) como último recurso. Es la implementación de spec 001 RF-34 y no se toca.
- `list.blade.php:181-184` tiene el botón "Compartir enlace" (`x-on:click="shareList()"`) en el header, junto a "Quitar de mis listas". Ese botón no tiene `id`; para el QR añado `#show-qr` a su lado.
- `public/sw.js` está en `CACHE_VERSION = 'v5'`; el QR no añade assets cacheables nuevos, así que no hace falta tocar el SW.
- No hay rutas de API nuevas por diseño (RF-8): Web Share y QR son 100 % cliente.

## Estructura de módulos

- `resources/js/list.js` → `renderQr()` (RF-1, RF-2, RF-5) y `toggleQr()` (RF-3); `#qr-code` se renderiza con la librería `qrcode` (canvas) dentro del componente.
- `resources/views/list.blade.php` → botón `#show-qr` junto al botón existente "Compartir enlace" en el header; contenedor `#qr-code` oculto por defecto (RF-4).
- `package.json` / `package-lock.json` → se añade `qrcode` en versión exacta pineada (`npm i --save-exact qrcode@1.5.4`), build de browser. Sus dependencias transitorias existen en la instalación, pero Vite solo incorpora la ruta usada por el navegador.
- `public/sw.js` → sin cambio de `CACHE_VERSION`.
- Docs: `docs/roadmap.md` (mover 005 del backlog a en curso y luego a Hecho), `AGENTS.md` (`#show-qr`, `#qr-code` reservados), `CHANGELOG.md` cuando se release.

## Modelo de datos

Sin cambios: no hay nuevas tablas ni columnas. El QR solo expone el slug público existente (constitución 7).

## Algoritmos no triviales

### Generación del QR en el cliente (RF-1, RF-2)

El código QR se genera en el cliente con la librería `qrcode` (build de browser) sobre un `<canvas>`:

1. Datos a codificar = enlace absoluto `window.location.origin + /l/<slug>`.
2. Codificación en **modo byte** (las URLs llevan minúsculas, que el modo alfanumérico del QR no admite), nivel de corrección de errores M (~15 % de recuperación). La librería elige versión, corrección de errores y máscara (0-7) automáticamente.
3. Render: `QRCode.toCanvas(canvas, url, { errorCorrectionLevel: 'M', margin, scale })` pinta el código dentro de un contenedor centrado. No se imprime la URL debajo.
4. Si la librería no puede generar el código, no se realiza ninguna petición
   de red ni se modifica el enlace visible bajo el QR.

No hay peticiones de red: todo es local (RF-1).

### Regeneración ante cambios de nombre (RF-5)

El bloque `list` del sync trae el nombre y el slug no cambia (es inmutable),
pero si `data.list.name` cambia mientras el QR está abierto, `renderQr` se
vuelve a ejecutar con el enlace actual. Es una defensa mínima para mantener
sincronizado el componente visible, aunque el payload siga siendo el mismo.

## Decisiones técnicas

| #   | Decisión                                                                                                          | Alternativa descartada y por qué                                                                                                                 |
| --- | ----------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| D1  | QR con la librería `qrcode@1.5.4` (build de browser), versión pineada.                                            | Reimplementar a mano: añade un algoritmo preciso y propenso a errores sin aportar valor al proyecto.                                             |
| D2  | Botón `#show-qr` al lado de "Compartir enlace" en el header, no dentro de `#list-menu`; alterna "Mostrar QR"/"Ocultar QR" y centra el código. | Menú desplegable: añade un clic extra para una acción de un toque en móvil (constitución 10). |
| D3  | El botón "Compartir enlace" existente (`shareList()`) se mantiene sin cambios; no se le añade `id` ni se duplica. | Crear `#share-web` aparte: habría dos botones de compartir que se solapan y lógica de visibilidad distinta (inconsistente con el código actual). |
| D4  | Cerrar el QR no recarga la lista ni toca el service worker.                                                       | Recargar para limpiar el DOM: innecesario; basta con `x-show`/`x-cloak` en Alpine.                                                               |
| D5  | `CACHE_VERSION` se queda en `v5`.                                                                                 | Subirla: el shell y `/build/*` no cambian; el QR se genera en cliente, no es un asset cacheado.                                                  |

## Contrato de interfaz

Todas sin autenticación (constitución 4), 100 % cliente, sin rutas de API nuevas.

| Elemento                             | `id`       | Comportamiento                                                                        |
| ------------------------------------ | ---------- | ------------------------------------------------------------------------------------- |
| Botón "Compartir enlace" (existente) | —          | `shareList()`: Web Share / portapapeles / URL en claro (spec 001 RF-34). Sin cambios. |
| Botón mostrar QR                     | `#show-qr` | Alterna visibilidad de `#qr-code` (RF-3, RF-4).                                       |
| Contenedor QR                        | `#qr-code` | Canvas centrado con el enlace absoluto; no muestra la URL debajo (RF-1, RF-2).          |

Ningún elemento nuevo usa fondos reservados para e2e (`AGENTS.md`). El aviso `.bg-green-50` (`shareNotice`) y `.bg-gray-100` (`shareUrl`) ya existen y se respetan.

## Riesgos

| Riesgo                                                                                 | Mitigación / por qué se acepta                                                                                                     |
| -------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------- |
| El slug largo hace el QR denso.                                                        | Los slugs son cortos (`[A-Za-z0-9_-]+`); la librería elige la versión mínima necesaria y el QR cabe en un tamaño razonable.        |
| Confundir al usuario con dos botones de compartir ("Compartir enlace" y "Mostrar QR"). | El QR no es "compartir" en el sentido de enviar a otro canal; es mostrar un código para escanear. Los textos dejan claro cada uno. |

## Estrategia de verificación

Puerta de cada tarea: `npx playwright test` (toca `resources/js/` y vistas Blade) y `vendor/bin/pint` sobre lo tocado. No hay backend nuevo, así que `php artisan test` solo se corre al cierre para asegurar que nada se rompió en la vista de lista.

- **Playwright** (`list-share.spec.js` o `list-qr.spec.js`):
    - Botón `#show-qr` existe junto a "Compartir enlace" en el header; alterna su texto y al hacer clic se muestra `#qr-code` centrado con el enlace absoluto, sin URL debajo (RF-1, RF-2, RF-4).
    - Cerrar el QR (`#show-qr` de nuevo o clic fuera) vuelve a la lista sin recarga (RF-3).
    - El botón "Compartir enlace" sigue funcionando: `shareList()` con chip `.bg-green-50` o URL `.bg-gray-100` sin cambios (RF-6).
    - No hay un segundo botón de Web Share ni duplicación de compartir (RF-7).
    - Ningún elemento nuevo usa fondos reservados para e2e.
- **Pest**: `php artisan test` completo al cierre, sin fallos (regresión de la vista de lista).
- **UI**: axe-core a 375 y 1280 px sin violaciones en la zona de compartir; targets de 44 px; pasada de teclado.
- **Cierre**: `vendor/bin/pint`, `docs/roadmap.md`, `AGENTS.md`, `CHANGELOG.md`, `/sdd:validate` → `validation.md`.

## Cobertura de RF

| RF         | Parte del plan que lo cubre                                                     |
| ---------- | ------------------------------------------------------------------------------- |
| RF-1, RF-2 | `renderQr()` en `list.js`; canvas centrado + enlace absoluto, sin URL debajo |
| RF-3       | `toggleQr()` alterna `#qr-code` sin recargar                                    |
| RF-4       | `#show-qr` y `#qr-code` con `id` propios, sin fondo reservado                   |
| RF-5       | `renderQr` se re-ejecuta si `data.list.name` cambia mientras el QR está abierto |
| RF-6       | `shareList()` sin cambios; `.bg-green-50` y `.bg-gray-100` respetados           |
| RF-7       | sin segundo botón de Web Share; spec 001 RF-34 sigue siendo la fuente           |
| RF-8       | sin envío ni recepción de datos de la lista; sin rutas nuevas                   |
