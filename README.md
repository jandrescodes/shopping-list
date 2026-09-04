<div align="center">

# 🛒 Lista de compras familiar

**PWA de listas de compras compartidas en familia, sin cuentas de usuario.**

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)
![PWA](https://img.shields.io/badge/PWA-instalable-5A0FC8?logo=pwa&logoColor=white)
![Tests](https://img.shields.io/badge/tests-Pest%203.8%20%2B%20Playwright-25A162?logo=pest&logoColor=white)
![Licencia](https://img.shields.io/badge/licencia-MIT-blue)

</div>

Crea una lista, comparte el enlace y todos en casa ven y editan los mismos ítems
en tiempo casi real. Cada lista se identifica por un `slug` no adivinable que es
la **única llave de acceso**: no hay registro, login ni contraseñas.

## Características

- **Sin cuentas.** El enlace de la lista es la credencial; nada que recordar.
- **Colaboración en vivo.** Agregar, editar, marcar y borrar ítems; los cambios
  se propagan entre dispositivos por _polling_ HTTP (sin WebSockets).
- **Resolución de conflictos** campo por campo con versionado optimista
  (última escritura gana, por campo).
- **Instalable como PWA.** Manifest + service worker; funciona en modo pantalla
  completa y mantiene visible la última lectura conocida sin conexión.
- **Mobile-first.** Diseñada y probada primero para pantalla de celular.
- **Hosting compartido.** Sin colas, schedulers ni procesos persistentes:
  corre en un plan de hosting compartido estándar.

## Stack

| Capa         | Tecnología                                   |
| ------------ | -------------------------------------------- |
| Backend      | PHP 8.2, Laravel 12, API REST                |
| Persistencia | MySQL vía Eloquent                           |
| Frontend     | Blade + Alpine.js, empaquetado con Vite      |
| PWA          | `manifest.json` + service worker (`sw.js`)   |
| Sync         | Polling HTTP con cursor opaco                |
| Tests        | Pest 3.8 (API), Playwright (capa de cliente) |

## Requisitos

- PHP **8.2**
- MySQL
- Composer
- Node.js con **npm**

## Instalación

```bash
git clone https://github.com/jandrescodes/shopping-list.git
cd shopping-list

composer install
npm install

cp .env.example .env
php artisan key:generate
# Ajusta DB_DATABASE / DB_USERNAME / DB_PASSWORD en .env
php artisan migrate

npm run build
php artisan serve
```

La app queda en `http://localhost:8000`.

## Comandos

| Acción                 | Comando                                               |
| ---------------------- | ----------------------------------------------------- |
| Servidor de desarrollo | `php artisan serve`                                   |
| Tests API/persistencia | `php artisan test` (Pest 3.8)                         |
| Tests de navegador     | `npx playwright test`                                 |
| Lint/formato           | `php artisan pint`                                    |
| Compilar assets        | `npm run build`                                       |
| Purgar lápidas         | `php artisan items:purge-tombstones --before=<fecha>` |

Los tests de API corren contra **MySQL**, no SQLite: copia
`.env.testing.example` a `.env.testing`, crea la BD `shopping_list_testing` y
ejecuta `npx playwright install chromium` la primera vez.

> El entorno usa PHP 8.2, que fija el techo de herramientas: Pest 4 y
> `pest-plugin-browser` exigen PHP 8.3+, así que los tests de la capa de cliente
> corren con Playwright directo, fuera de la suite de Pest.

## Estructura

```
app/Http/Controllers/Api/   Controladores REST (listas, ítems)
app/Models/                  ShoppingList, Item
routes/api.php               Endpoints de la API
resources/js/app.js           Bootstrap global + registro del service worker
resources/js/home.js          Glue de la home (crear lista, "mis listas")
resources/js/list.js          Cliente Alpine (edición + polling)
resources/views/             Vistas Blade
public/manifest.json         Manifest PWA
public/sw.js                 Service worker
lang/es/                     Textos en español
docs/                        Constitución, roadmap y despliegue
specs/                       Especificaciones por feature (SDD)
.github/workflows/           CI (tests + Pint) y deploy a Hostinger
CHANGELOG.md                 Historial de versiones
```

## Documentación

- [`CHANGELOG.md`](CHANGELOG.md) — historial de versiones (Keep a Changelog).
- [`AGENTS.md`](AGENTS.md) — convenciones, comandos y arquitectura (fuente única).
- [`docs/constitution.md`](docs/constitution.md) — principios innegociables.
- [`docs/roadmap.md`](docs/roadmap.md) — estado y backlog.
- [`docs/deploy.md`](docs/deploy.md) — despliegue en hosting compartido.
- [`specs/`](specs/) — especificaciones por feature (`spec.md`, `plan.md`,
  `tasks.md`, `validation.md`).

El proyecto sigue **Spec-Driven Development**: cada feature nace como
`specs/NNN-<slug>/` con spec, plan y tareas antes de tocar código, y no se cierra
hasta que la fase de validación deja su `validation.md`.

## Licencia

<div align="center">

[MIT](LICENSE) &nbsp;·&nbsp; Hecho con ❤️ en Santa Cruz de la Sierra, Bolivia

</div>
