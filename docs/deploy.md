# Despliegue — Lista de compras familiar

Destino: **hosting compartido Premium de Hostinger** (sin procesos persistentes,
sin colas, sin scheduler, sin cron). PHP 8.2, MySQL.

El despliegue lo hace el workflow **`.github/workflows/deploy.yml`**: construye
en el runner de GitHub Actions y sube los archivos **por `rsync` sobre SSH**. El
servidor **nunca habla con git** (nada de `git clone` / `git pull` en el host):
eso obligaría a mantener una Deploy Key aparte solo para autenticar el servidor
contra el remoto. El runner ya tiene el código y la conexión SSH; se reutiliza.

## Datos del entorno (Hostinger)

> Repo público: aquí solo van **placeholders**. Los valores reales (cuenta,
> dominio, ruta, nombre y usuario de la BD, contraseña) viven en los secretos
> del entorno `production` de GitHub y en el `.env` del servidor — nunca en el
> repo.

Cuenta de hosting compartida `<cuenta>` (misma que el resto de sitios: el **SSH
es por cuenta**, así que sirven la misma llave/host/usuario/puerto que ya usás
para los otros proyectos — solo cambia la ruta).

| Recurso                 | Valor                                                            |
| ----------------------- | --------------------------------------------------------------- |
| Dominio                 | subdominio `*.hostingersite.com` creado para el proyecto        |
| `HOSTINGER_APP_PATH`    | `/home/<cuenta>/domains/<dominio>/app`                          |
| Document root del sitio | `app/public` (se fija en hPanel, ver abajo)                     |
| PHP                     | 8.2 (fijado; pdo_mysql, mbstring, bcmath, intl, gd OK)          |
| BD / usuario            | `<cuenta>_shopping_list` / `<cuenta>_shopping`                  |
| Host / puerto BD        | `localhost` / `3306`                                            |

La contraseña de la BD se generó al crearla y va **solo** en el `.env` del
servidor (paso 4). No se versiona ni se guarda como secreto de GitHub.

## Flujo automático

**Disparadores:**

- Publicar un _release_ en GitHub (`release: published`) — el flujo normal:
  taggear `1.0.0`, redactar el release desde el `CHANGELOG.md`, publicarlo.
- `workflow_dispatch` manual desde la pestaña Actions, indicando el tag/rama.

**Qué hace el workflow:**

1. `test` — Pest contra MySQL 8.0 (misma base que CI). Si falla, no se despliega.
2. `deploy` (entorno `production`; primer paso verifica los secretos y aborta
   con un error claro si falta alguno):
    - `composer install --no-dev --optimize-autoloader` en el runner, con
      `vendor/` cacheado por `hashFiles('composer.lock')` (key
      `composer-prod-*`, separada de la del job `test` porque `--no-dev` da un
      árbol de dependencias distinto).
    - `npm ci && npm run build` en el runner (genera `public/build/`); el
      registro de npm queda cacheado vía `cache: npm` de `actions/setup-node`
      (`npm ci` siempre reinstala `node_modules/` desde cero, así que cachear
      ese directorio no ahorra nada — lo que evita la descarga de red es la
      caché del registro).
    - `rsync -az --delete` al `$HOSTINGER_APP_PATH` del servidor, excluyendo
      `.git/`, `.github/`, `docs/`, `specs/`, `tests/`, `node_modules/`, `.env*`,
      `/storage/`, `playwright.config.js`, `phpunit.xml`. **`vendor/` y
      `public/build/` sí se suben** (ya construidos).
    - Por SSH en el servidor: `php artisan migrate --force`, `config:cache`,
      `route:cache`, `view:cache`, `optimize`.
    - Health check: `GET https://$HOSTINGER_DOMAIN/up` (endpoint de salud de
      Laravel), 6 reintentos.

`concurrency: deploy-production` serializa despliegues solapados.

## Secretos del entorno `production`

El job `deploy` declara `environment: production`, así que los secretos van en
**Settings → Environments → `production` → Environment secrets** (no en los
secretos generales del repo). Crea el entorno `production` si no existe.

| Secreto              | Contenido                                                             | Obligatorio |
| -------------------- | -------------------------------------------------------------------- | ----------- |
| `HOSTINGER_SSH_KEY`  | clave **privada** OpenSSH (la misma de los otros sitios de la cuenta, o una nueva — ver abajo) | sí |
| `HOSTINGER_SSH_HOST` | host SSH de la cuenta de hosting (el de tus otros deploys)           | sí          |
| `HOSTINGER_SSH_USER` | usuario SSH de la cuenta (`uXXXXXXXXX`)                              | sí          |
| `HOSTINGER_APP_PATH` | `/home/<cuenta>/domains/<dominio>/app`                              | sí          |
| `HOSTINGER_SSH_PORT` | normalmente `65002` (por defecto si no se define)                   | no          |
| `HOSTINGER_DOMAIN`   | el subdominio del proyecto (para el health check)                   | no          |

Sin alguno de los cuatro obligatorios el job `deploy` **falla en el primer paso**
con un mensaje que dice cuál falta.

Si añades _required reviewers_ al entorno `production`, cada despliegue quedará
en pausa esperando tu aprobación manual en la pestaña Actions — opcional, útil
como último cerrojo antes de tocar producción.

`HOSTINGER_APP_PATH` es la **raíz de la app Laravel** (`…/app`), no `public_html`.

### Generar la llave SSH de despliegue

```bash
ssh-keygen -t ed25519 -C "deploy-shopping-list" -f deploy_key -N ""
```

- Contenido de `deploy_key.pub` → `~/.ssh/authorized_keys` del usuario SSH en
  Hostinger (hPanel → Avanzado → Acceso SSH, o `cat deploy_key.pub >> ~/.ssh/authorized_keys`).
- Contenido de `deploy_key` (privada) → secreto `HOSTINGER_SSH_KEY`.
- Borrar ambos archivos locales después.

## Preparación del servidor (una sola vez)

Ya hecho vía API de Hostinger: sitio creado, PHP fijado en 8.2, BD + usuario
creados. Queda:

1. **Acceso SSH** ya activo en la cuenta (se usa el mismo de los otros sitios).
   Añade la clave pública de despliegue a `~/.ssh/authorized_keys` si generás una
   nueva (ver "Generar la llave SSH").

2. **Crear los directorios `app/`, `storage/` y `bootstrap/cache/`** (por SSH;
   `$D` = el directorio del dominio, `~/domains/<dominio>`):

    ```bash
    mkdir -p "$D"/app/storage/framework/{cache/data,sessions,views} "$D"/app/storage/logs
    mkdir -p "$D"/app/bootstrap/cache
    chmod -R ug+rwX "$D"/app/storage "$D"/app/bootstrap/cache
    ```

    `storage/` y `bootstrap/cache/` quedan fuera del `rsync` para no pisarse en
    cada deploy; hay que crearlos a mano esta vez.

3. **Document root del sitio → `app/public`.** En hPanel: Sitios web → el
   dominio → Avanzado → _Cambiar carpeta raíz del sitio_ → `…/<dominio>/app/public`.
   (Alternativa por SSH si el panel no lo permite:
   `cd "$D" && rm -rf public_html && ln -s app/public public_html`.)

4. **`.env` de producción** en `app/.env` — se crea a mano desde la plantilla
   `.env.production.example` (que sí llega por rsync; el rsync excluye `.env`):

    ```bash
    cd "$D"/app
    cp .env.production.example .env
    php artisan key:generate
    # editar .env: APP_URL, DB_DATABASE, DB_USERNAME, DB_PASSWORD
    ```

    `APP_URL` = `https://<dominio>`; `DB_*` = los datos de la BD creada
    (nombre y usuario prefijados con la cuenta; contraseña la generada al
    crearla). El resto de la plantilla (`APP_ENV=production`, `APP_DEBUG=false`,
    `DB_HOST=localhost`, `SESSION_DRIVER=cookie`, `CACHE_STORE=database`,
    `QUEUE_CONNECTION=sync`) ya viene correcto.

5. **HTTPS**: certificado SSL activo en hPanel + redirección HTTP→HTTPS. Con
   `APP_URL=https://…` todas las URLs que genera la app salen en `https`.

6. **Primer despliegue**: lanzar el workflow (`workflow_dispatch`). La tabla
   `cache` que necesita `CACHE_STORE=database` la crea `php artisan migrate`
   (migración por defecto de Laravel 12).

## Despliegue manual (fallback, sin CI)

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
rsync -az --delete \
  -e "ssh -p 65002" \
  --exclude='.git/' --exclude='.github/' --exclude='docs/' --exclude='specs/' \
  --exclude='tests/' --exclude='node_modules/' \
  --exclude='.env' --include='.env.production.example' --exclude='.env.*' \
  --exclude='/storage/' --exclude='playwright.config.js' --exclude='phpunit.xml' \
  ./ <user>@<host>:<APP_PATH>/
ssh -p 65002 <user>@<host> "cd <APP_PATH> && php artisan migrate --force && \
  php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan optimize"
```

## Mantenimiento manual

No hay scheduler ni cron. Las lápidas de ítems borrados (`deleted_at` no nulo)
**no se purgan solas**. Cada cierto tiempo, a mano por SSH:

```bash
php artisan items:purge-tombstones --before=$(date -d '3 months ago' +%F)
```

Borra físicamente (`forceDelete`) las lápidas anteriores a esa fecha y reporta el
conteo. Sin `--before` aborta.

## Verificación post-despliegue (manual)

- [ ] `https://<dominio>/` carga y muestra el formulario de crear lista.
- [ ] `GET https://<dominio>/up` → 200 (health check de Laravel).
- [ ] Crear una lista → el enlace devuelto arranca por `https://<dominio>/l/`.
- [ ] Lighthouse (Chrome Android o DevTools) sobre `/l/{slug}`: **"installable"**.
- [ ] Abrir la lista con conexión, luego cortar la red: el shell y la última
      lectura siguen visibles (service worker).
- [ ] Primer arranque sin conexión (perfil nuevo, sin caché): se ve la página
      `/offline` mínima.
- [ ] Demo en dos celulares: agregar / marcar / editar / borrar un ítem en uno y
      verlo en el otro en ≤ 5 s.
- [ ] `GET /robots.txt` contiene `Disallow: /l/`; `GET /l/{slug}` responde con
      `X-Robots-Tag: noindex, nofollow`.
