# Tareas — Spec 005

Tareas de <30 min, ordenadas por dependencia. Cada una: sus RF, un checkbox y
una línea "Done when:" verificable. Derivadas de `plan.md`; los RF salen de
`spec.md`.

Puerta de verificación por tarea (`AGENTS.md`): `npx playwright test` cuando la
tarea toque `resources/js/` o vistas Blade; `vendor/bin/pint` sobre lo tocado.
`php artisan test` se corre al cierre para asegurar que no hubo regresión en la
vista de lista. Ninguna tarea se marca hecha en rojo.

Reglas que atraviesan todo el bloque:

- **No hay rutas de API nuevas** (RF-8): ninguna tarea añade líneas a
  `routes/api.php` ni controladores.
- **No se toca `shareList()` ni el botón "Compartir enlace" existente** (RF-6,
  RF-7): Web Share ya está implementado en spec 001 RF-34 y `list.js:239-265`.
- **Clases de fondo reservadas**: ningún elemento nuevo usa
  `bg-green-50`, `bg-gray-100`, `bg-amber-50`, `bg-blue-50`, `bg-sky-50`,
  `bg-teal-50`, `bg-orange-50` ni `bg-red-50`; cada componente lleva su
  `id` (`#show-qr`, `#qr-code`).
- **Dependencia única y pineada** (plan D1): el QR usa `qrcode@1.5.4` (build de
  browser), añadida con `npm i --save-exact`; no se reimplementa el algoritmo a
  mano.

## Cliente: código QR

- [x] T0. Baseline: ejecutar `php artisan test`, `npx playwright test` y
      `vendor/bin/pint --test` sin cambios. (Estado de partida del plan)
      Done when: las tres comprobaciones pasan y sus conteos quedan registrados
      en la evidencia de implementación o validación.

- [x] T1. `renderQr()` en `list.js`: genera el QR del enlace absoluto con
      la librería `qrcode@1.5.4` (build de browser, `toCanvas`, nivel de
      corrección M) dentro de `#qr-code`, sin mostrar la URL debajo. (RF-1,
      RF-2, RF-4)
      Done when: `npx playwright test list-qr.spec.js` pasa el caso de "QR muestra
      el enlace absoluto sin texto adicional debajo"; el canvas contiene
      módulos visibles y el build termina correctamente.

- [x] T2. `toggleQr()` en `list.js` y botón `#show-qr` en `list.blade.php`,
      junto al botón existente "Compartir enlace" en el header: alternan la
      visibilidad de `#qr-code`, el texto "Mostrar QR"/"Ocultar QR" y el
      centrado del código, sin recargar la lista ni tocar el service worker.
      (RF-3, RF-4)
      Done when: `npx playwright test list-qr.spec.js` pasa los casos de mostrar
      y ocultar QR, texto alterno y ausencia de navegación.

- [x] T3. Regenerar el QR si el nombre cambia mientras está abierto (defensa):
      `renderQr` se vuelve a ejecutar cuando el bloque `list` del sync trae un
      nombre distinto. (RF-5)
      Done when: `npx playwright test list-qr.spec.js` pasa el caso de
      "cambiar nombre regenera el QR abierto".

## Cliente: compartir existente y regresiones

- [x] T4. El botón "Compartir enlace" existente (`shareList()`) sigue
      funcionando: `navigator.share` si hay soporte, portapapeles + chip
      `.bg-green-50`, o URL en claro `.bg-gray-100` como último recurso; no hay
      un segundo botón de Web Share ni duplicación de compartir. (RF-6, RF-7)
      Done when: `npx playwright test list-qr.spec.js list-share.spec.js` pasa
      el caso de compartir y confirma que `.bg-green-50` y `.bg-gray-100` de los
      tests existentes siguen resolviendo un solo elemento cada uno.

## Accesibilidad y cierre

- [x] T5. Pasada de accesibilidad: axe-core a 375 y 1280 px sobre la zona de
      compartir (botón "Compartir enlace", botón QR, contenedor QR), targets de
      44 px, pasada de teclado completa y contraste. (RNF; RF-4)
      Done when: axe no reporta violaciones en ninguno de los dos anchos y la
      pasada de teclado queda registrada en `list-qr.spec.js`.

## Cierre de la feature

_Checkboxes sueltos, fuera de la numeración `Tn` — no se implementan con
`/sdd:implement`._

- [x] Suite completa: `php artisan test`, `npx playwright test` y
      `vendor/bin/pint --test` pasan sin cambios pendientes.
- [x] Validación SDD: ejecutar `/sdd:validate` para generar
      `validation.md`, recorrer RF-1 a RF-8 y emitir el veredicto.
- [x] Documentación de cierre: `/sdd:validate` actualiza `docs/roadmap.md`;
      confirmar que `AGENTS.md` contiene `#show-qr` y `#qr-code`; añadir la
      entrada de `CHANGELOG.md` al cerrar la versión.
- [x] Verificación complementaria en dos navegadores móviles independientes:
      abrir en el segundo navegador la URL codificada por el QR, confirmar que
      ambos acceden a la misma lista, que el polling sincroniza un ítem y que
      "Compartir enlace" conserva su comportamiento.
      La cámara y el escaneo óptico en dos celulares físicos quedan pendientes.

## Cobertura RF → tarea

| RF   | Tareas                      |
| ---- | --------------------------- |
| RF-1 | T1                          |
| RF-2 | T1                          |
| RF-3 | T2                          |
| RF-4 | T1, T2                      |
| RF-5 | T3                          |
| RF-6 | T4                          |
| RF-7 | T4 (por diseño)             |
| RF-8 | T1, T2, T4 y validación SDD |
