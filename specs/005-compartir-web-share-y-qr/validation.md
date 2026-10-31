# Validación — Spec 005

**Date:** 2026-10-31

## Verificación ejecutada

| Comprobación                           | Resultado                                                                                                                                  |
| -------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| `php artisan test`                     | PASS — 267 tests, 1437 assertions                                                                                                          |
| `npx playwright test`                  | PASS — 118 tests                                                                                                                           |
| `vendor/bin/pint --test`               | PASS                                                                                                                                       |
| Axe en 375 × 667 px                    | PASS — sin violaciones                                                                                                                     |
| Axe en 1280 × 800 px                   | PASS — sin violaciones                                                                                                                     |
| Operación por teclado                  | PASS — abrir/cerrar conserva el foco y actualiza `aria-expanded`                                                                           |
| Build de frontend                      | PASS — `npm run build`                                                                                                                     |
| Dos navegadores móviles independientes | PASS — el segundo abrió la URL del QR; un ítem creado allí apareció en el primero por polling y «Compartir enlace» mostró «Enlace copiado» |

## Recorrido RF por RF

| RF   | Evidencia                                                                                                     | Veredicto |
| ---- | ------------------------------------------------------------------------------------------------------------- | --------- |
| RF-1 | `list-qr.spec.js`: muestra el QR del enlace absoluto; `renderQr()` usa canvas y no realiza peticiones de red. | PASS      |
| RF-2 | `list-qr.spec.js`: canvas visible, contenedor centrado y ausencia de URL debajo.                              | PASS      |
| RF-3 | `list-qr.spec.js`: mostrar/ocultar sin navegación, con texto alterno y estado `aria-expanded`.                | PASS      |
| RF-4 | `list-qr.spec.js`: existen `#show-qr` y `#qr-code`; no se reutilizan fondos reservados.                       | PASS      |
| RF-5 | `list-qr.spec.js`: un cambio remoto de nombre regenera el QR abierto.                                         | PASS      |
| RF-6 | `list-qr.spec.js` y `list-share.spec.js`: el compartir existente mantiene sus fallbacks.                      | PASS      |
| RF-7 | La implementación conserva un único botón `shareList()`; la regresión de compartir pasa.                      | PASS      |
| RF-8 | La feature no añade rutas ni envía datos de la lista; QR y compartir operan sobre el enlace público.          | PASS      |

## Requisitos no funcionales

| RNF                          | Evidencia                                                                    | Veredicto |
| ---------------------------- | ---------------------------------------------------------------------------- | --------- |
| Cliente sin API nueva        | Diff sin cambios en rutas o controladores; suite completa en verde.          | PASS      |
| Mobile-first y accesibilidad | Axe en ambos viewports, teclado y targets mínimos de 44 px.                  | PASS      |
| Dependencias pineadas        | `qrcode@1.5.4` y `@axe-core/playwright@4.14.0` en `package.json` y lockfile. | PASS      |

## Verificación manual complementaria

- Se verificó el flujo equivalente en dos sesiones independientes de Chromium
  con viewport móvil (360 × 844): la primera mostró el QR y la segunda abrió
  `/l/{slug}` usando exactamente la URL codificada; ambos navegadores vieron la
  misma lista y sincronizaron un ítem mediante polling.
- En el segundo navegador, «Compartir enlace» mostró el aviso existente
  «Enlace copiado».
- Sigue pendiente la confirmación en dos celulares físicos, porque esta prueba
  no valida cámara, enfoque ni lectura óptica del QR.

## Veredicto

**Spec fulfilled (spec cumplida) en la verificación automatizada y en dos navegadores móviles
emulados.** RF-1 a RF-8 tienen evidencia en verde y la documentación de cierre
está actualizada. La verificación con dos celulares físicos queda pendiente
solo para validar la cámara y el escaneo óptico.
