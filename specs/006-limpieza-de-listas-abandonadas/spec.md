# Spec 006 — Limpieza de listas abandonadas

**Estado:** cerrada — validada el 2026-11-01

## Contexto y objetivo

Las listas de compras que un hogar deja de usar se acumulan en la base de
datos y no tienen dueño que las borre: la baja manual por slug existe, pero
no hay retiro periódico.

Esta feature añade el comando de mantenimiento `lists:purge-inactive` que
físicamente elimina las listas sin uso ni modificación durante el periodo de
inactividad configurado (variable de entorno).

No cambia el contrato de la API ni el flujo de sincronización por polling:
las listas purgadas dejan de existir para los dispositivos que las pidan por
slug.

## Usuarios / actores

No aparecen actores nuevos. El responsable del hogar ejecuta el purge desde
el mantenimiento; el resto de los miembros simplemente deja de ver las listas
borradas en sus próximas sincronizaciones.

## Historias de usuario

- H1: Como responsable del hogar quiero purgar las listas abandonadas para
  no administrarlas manualmente.
- H2: Como usuario quiero que el purge no toque las listas que aún se usan.

## Requisitos funcionales (criterios de aceptación en EARS)

### Definición de inactividad

- RF-1: WHILE a shopping list has had no usage or modification activity during the configured inactivity period, THE SYSTEM shall consider it abandoned.
- RF-2: WHEN a list is modified (item created, updated, marked or unmarked as purchased, list renamed, currency or budget changed), THE SYSTEM shall renew its activity.

### Purga y reporte

- RF-3: WHEN an abandoned list is purged, THE SYSTEM shall physically delete the list and all its items (active and tombstones alike).
- RF-4: WHEN the purge runs, THE SYSTEM shall report the number of purged lists.

### Configuración y errores

- RF-5: THE SYSTEM shall read the inactivity period from configuration.
- RF-6: IF the inactivity period is not configured, THEN THE SYSTEM shall abort the purge with an error.

## Requisitos no funcionales

- **Sin cambios en el contrato**: el purge no añade rutas ni altera el contrato ni el rate limiting de la API (constitución 5).
- **Sin scheduler en la app**: el comando se expone para su ejecución por el cron de hPanel; la aplicación no registra scheduler (constitución 5).
- **Persistencia**: borrado físico (`forceDelete`) de la lista y sus ítems, sin introducir soft deletes nuevos.

## Casos límite

- No hay listas abandonadas: el purge termina en éxito reportando 0.
- El periodo no está configurado: el purge aborta con error (RF-6).
- Lista modificada hace poco: no se purga aunque exista desde antes (RF-1, RF-2).
- Lista con ítems (activos o tombstones): se borran junto con la lista (RF-3).

## Fuera de alcance

- No cambia el contrato de sincronización, rutas o rate limiting.
- No incluye notificaciones, historial de listas ni categorías (constitución 7 y descartes del roadmap).
- No incluye la limpieza de tombstones como responsabilidad de esta feature: ya existe `items:purge-tombstones` (spec 001).

## Criterios de finalización

- [ ] RF-1 a RF-6 verificados con tests Pest del comando `lists:purge-inactive`.
- [ ] El purge no toca listas activas.

## Dudas abiertas

- [NEEDS CLARIFICATION: el plan de hosting permite el cron de hPanel para ejecutar `lists:purge-inactive`? → RESUELTA el 2026-11-01: la operación `hosting_cron-jobs_create` de la API de Hostinger respondió exitosamente y se creó el job (uid `fGLEKPsN2B`, `0 3 * * *`) que ejecuta `php /home/u495480405/domains/paleturquoise-llama-677683.hostingersite.com/app/artisan lists:purge-inactive`. El plan permite cron.

## Clarificaciones

### Fase 2 — spec inicial

- P1 (alcance): solo el comando de purga; sin cambios de UI ni de contrato de la API.
- P2 (definición): "sin uso ni modificación" — la actividad se renueva con modificaciones; leer no la renueva.
- P3 (periodo): configurable vía variable de entorno.
- P4 (acción): borrado físico (lista + ítems); reporte de conteo.
- P5 (errores): abortar sin periodo configurado.

### Fase 3 — verificación de hosting (2026-11-01)

- P7 (infra): el cron de hPanel quedó verificado vía API de Hostinger (`hosting_cron-jobs_create`, uid `fGLEKPsN2B`, schedule `0 3 * * *`) y el plan lo permite; el job apunta al artisan del sitio de la app (`paleturquoise-llama-677683.hostingersite.com`).
