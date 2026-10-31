# Spec 005 — Código QR para compartir la lista

**Estado:** cerrada — validada el 2026-10-31

## Contexto y objetivo

La lista ya se comparte con el botón "Compartir enlace" (spec 001 RF-34): abre la hoja nativa del dispositivo (`navigator.share`) y, si no hay, copia al portapapeles o muestra la URL en claro. Eso resuelve compartir desde el móvil, pero no el caso de casa: otro celular que está junto al primero no puede recibir el enlace sin que alguien lo escriba o lo pegue.

Esta feature añade un **código QR** del enlace público de la lista (`/l/{slug}`), escaneable con la cámara de cualquier otro celular, junto al botón existente de compartir. No cambia quién puede ver la lista: el slug sigue siendo la única llave (constitución 4).

No introduce conceptos nuevos de dominio: no hay contactos, no hay envío de correo, no hay historial de quién recibió la lista ni notificaciones push (constitución 5 y 7).

## Usuarios / actores

No aparecen actores nuevos. Quien tiene el enlace de la lista puede mostrar su QR, igual que ya puede compartirlo (constitución 4). El que escanea entra como cualquier visitante con el slug.

## Historias de usuario

- H1: Como quien está en casa con otro celular quiero escanear un QR para abrir la lista sin escribir el slug.
- H2: Como quien comparte desde el móvil quiero seguir usando "Compartir enlace" exactamente como hoy, sin que el QR le estorbe ni duplique la acción.

## Requisitos funcionales (criterios de aceptación en EARS)

### Código QR (nuevo)

- RF-1: CUANDO el usuario pulsa "Mostrar QR" junto al botón de compartir, EL SISTEMA renderiza un código QR del enlace absoluto de la lista (`/l/{slug}`) sin peticiones de red (generado en el cliente).
- RF-2: EL SISTEMA muestra el QR centrado junto al botón de compartir, sin
  mostrar la URL debajo ni reemplazar el enlace existente.
- RF-3: CUANDO el usuario cierra el QR, EL SISTEMA vuelve a la vista de lista
  sin recargar y el botón vuelve a decir "Mostrar QR"; mientras está abierto,
  el botón dice "Ocultar QR".
- RF-4: EL botón de QR lleva un `id` propio (`#show-qr`) y el contenedor del QR lleva un `id` propio (`#qr-code`), sin reutilizar clases de fondo reservadas para los tests e2e (`AGENTS.md`).
- RF-5: CUANDO cambia el nombre de la lista, EL SISTEMA regenera el QR si está
  abierto. El slug no cambia: la regeneración es una defensa para mantener
  sincronizado el componente visible.

### Compartir existente (sin cambios)

- RF-6: EL botón "Compartir enlace" existente (acciona `shareList()` en `list.js`) se mantiene sin cambios: `navigator.share` si hay soporte, portapapeles + aviso `.bg-green-50` si no, y URL en claro `.bg-gray-100` como último recurso (spec 001 RF-34).
- RF-7: Web Share ya está cubierto por spec 001 RF-34; este spec no lo re-implementa ni añade un segundo botón de compartir.
- RF-8: NI Web Share ni QR envían ni reciben datos de la lista: solo exponen el enlace público que ya existe (constitución 4, 7).

## Requisitos no funcionales

- **Sin autenticación**: no hay login ni rastreo de quién compartió o escaneó (constitución 4).
- **Sin peticiones nuevas**: QR y Web Share son 100 % cliente; no tocan `routes/api.php` ni consumen cupo de rate limiting.
- **Dependencia justificada (regla 1 de la constitución)**: el QR se genera con la librería `qrcode` (build de browser, versión pineada). Se evita reimplementar un algoritmo preciso y propenso a errores; las dependencias transitorias de CLI permanecen en la instalación, pero no se incorporan al bundle del navegador.
- **Avisos y componentes nuevos**: llevan `id` o clase semántica propia y no reutilizan los fondos reservados para los tests e2e (`bg-green-50`, `bg-gray-100`, `bg-amber-50`, `bg-blue-50`, `bg-sky-50`, `bg-teal-50`, `bg-orange-50`, `bg-red-50`, `AGENTS.md`).
- **Mobile-first**: Web Share es el camino preferido en móvil; el QR es para casa (otro celular en la misma red o sin red, solo necesita ver la pantalla). El código se presenta centrado para facilitar el escaneo.
- **Idioma**: interfaz y avisos en español; código en inglés.

## Casos límite

- **Navegador de escritorio**: no hay `navigator.share` (ya lo maneja `shareList()`), pero el QR sigue mostrándose y escaneándose.
- **El usuario deniega el compartir**: no aplica al QR (no hay hoja de compartir); solo se muestra el código.
- **Lista con slug largo**: el QR sigue siendo escaneable; no se añade texto adicional debajo del código.
- **Sin red en el móvil que escanea**: el QR apunta al dominio de la lista; si no hay red, no carga (comportamiento igual que copiar el enlace y pegarlo sin red).
- **Nombre de la lista con emojis o caracteres especiales**: el QR codifica el enlace correctamente.

## Fuera de alcance

- Enviar la lista por correo, SMS, WhatsApp directo o cualquier canal que no sea `navigator.share` ni QR.
- Historial de quién recibió o abrió la lista (constitución 7).
- Notificaciones push al recibir una lista compartida (constitución 5).
- Cambiar el slug al compartir; el slug es inmutable tras crear la lista (spec 001).
- Purga de listas por cron (choca con constitución 5) y modo oscuro (estético, baja prioridad).

## Criterios de finalización

- [x] RF-1 a RF-5 verificados con tests Playwright (capa de cliente).
- [x] RF-6 y RF-7 verificados por regresión: el botón "Compartir enlace" existente sigue funcionando igual (chip y aviso actuales).
- [x] El QR renderiza el enlace absoluto y es escaneable; abrirlo y cerrarlo no recarga la lista.
- [x] `npx playwright test` en verde, `vendor/bin/pint` sin cambios pendientes, `php artisan test` completo verde (regresión de la vista de lista).
- [x] `docs/roadmap.md` actualizado y `validation.md` generado en `/sdd:validate`.

## Dudas abiertas

Ninguna. La única duda (ubicación del botón QR) quedó resuelta en P2: junto a "Compartir enlace" en el header.

## Clarificaciones

### Fase 2 — spec inicial

- P1 (alcance): solo código QR; Web Share ya existe en spec 001 RF-34 y en `list.js` (`shareList()`), así que no se re-especifica.
- P2 (ubicación): el botón QR se coloca junto a "Compartir enlace" en el header de la lista (no dentro de `#list-menu`), para que sea visible sin abrir un menú. El QR se centra al abrirse y no muestra la URL debajo.
- P6 (estado): el texto del botón alterna entre "Mostrar QR" y "Ocultar QR" según la visibilidad del código.
- P3 (QR con librería justificada): se genera en el cliente con la librería `qrcode` (build de browser, versión pineada), añadida bajo la regla 1 de la constitución (dependencia justificada por corrección). No se reimplementa el algoritmo a mano.
- P4 (sin rutas nuevas): no se toca `routes/api.php`; todo es cliente sobre el enlace público existente.
- P5 (modo de codificación): el QR se codifica en **modo byte** (las URLs llevan minúsculas, que el modo alfanumérico del QR no admite); la librería elige versión, corrección de errores y máscara (0-7) automáticamente.
