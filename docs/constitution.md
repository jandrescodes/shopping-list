# Constitución — Lista de compras familiar

Principios innegociables. Toda spec, plan y tarea debe cumplirlos.

1. **Stack fijo**: PHP 8.2, Laravel 12, MySQL. Frontend Blade + Alpine.js/vanilla,
   empaquetado como PWA. No se añaden dependencias de runtime sin justificarlas
   en el plan.
2. **La spec manda**: ningún comportamiento se implementa si no está en la spec
   activa de `specs/`. Si falta una decisión, se detiene el trabajo y se pregunta.
3. **Tests como puerta**: cada tarea termina con sus tests (Pest/PHPUnit) en
   verde. Prohibido avanzar con tests en rojo.
4. **Sin autenticación**: no hay tabla de usuarios, login, roles ni permisos. El
   `slug` no adivinable de la lista es la única llave de acceso. Añadir cualquier
   forma de auth exige cambiar esta constitución primero.
5. **Compatible con hosting compartido**: sin WebSockets, Reverb, procesos
   persistentes, colas ni schedulers. La sincronización entre dispositivos es por
   polling HTTP.
6. **Persistencia**: MySQL vía Eloquent. `snake_case` en BD, `camelCase` en PHP.
   Las URLs públicas usan `slug`, nunca el `id` autoincremental. El `.env` nunca
   se sube al repositorio.
7. **Alcance acotado**: es una app doméstica de listas de compras. No es
   multi-tenant, no lleva historial/auditoría (más allá de un campo de texto
   libre opcional), no compite con gestores de tareas generales.
8. **Idioma**: código, identificadores, rutas y nombres de test en inglés;
   contenido visible en la app y documentación en español.
9. **Validación**: toda entrada del usuario se valida con Form Requests de
   Laravel antes de tocar la capa de datos.
10. **Mobile-first**: la interfaz se diseña y prueba primero para pantalla de
    celular.
