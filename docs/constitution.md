# Constitución — Lista de compras familiar

Principios innegociables. Toda spec, plan y tarea debe cumplirlos. La numeración
es estable: specs y planes la citan.

1. **Stack fijo**: PHP 8.2, Laravel 12, MySQL; Blade + Alpine.js como PWA. Sin
   dependencias de runtime nuevas sin justificarlas en el plan.
2. **La spec manda**: nada se implementa si no está en la spec activa. Si falta una
   decisión, se detiene el trabajo y se pregunta.
3. **Verificación como puerta**: cada tarea termina con `php artisan test` (Pest) y,
   si toca `resources/js/`, `npx playwright test` en verde. Nunca avanzar en rojo.
4. **Sin autenticación**: no hay usuarios, login ni roles. El `slug` no adivinable
   es la única llave de acceso; añadir auth exige cambiar esta constitución antes.
5. **Hosting compartido**: sin WebSockets, procesos persistentes, colas ni
   schedulers. La sincronización entre dispositivos es por polling HTTP.
6. **Persistencia**: MySQL vía Eloquent, `snake_case` en BD y `camelCase` en PHP.
   URLs públicas por `slug`, nunca por `id`. El `.env` nunca se sube.
7. **Alcance**: app doméstica de listas de compras. NO es multi-tenant, no lleva
   historial/auditoría ni compite con gestores de tareas.
8. **Idioma**: código, identificadores, rutas y tests en inglés; interfaz, specs y
   documentación en español.
9. **Capas y validación**: reglas de negocio en modelos y `App\Support`;
   controladores y vistas delgados; toda entrada se valida con Form Requests.
10. **Mobile-first**: la interfaz se diseña y prueba primero para celular.
