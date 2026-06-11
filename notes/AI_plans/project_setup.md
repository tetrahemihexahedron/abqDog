# Phase 1 Compose and web setup

Update the existing Compose/web configuration after completing:

1. `notes/AI_plans/frontend_setup.md`
2. `notes/AI_plans/backend_setup.md`

## 1. Start from the repository root

```sh
cd /workspaces/abqDog
```

Expected inputs:

- `frontend/Dockerfile`
- `frontend/package.json`
- `frontend/pnpm-lock.yaml`
- `backend/Dockerfile`
- `backend/composer.json`
- `backend/composer.lock`
- `backend/public/index.php`
- `compose.yml`
- root `Caddyfile`

## 2. Move the Caddyfile into `web/`

Use `web/` for Caddy-specific files:

```sh
mkdir -p web
git mv Caddyfile web/Caddyfile || mv Caddyfile web/Caddyfile
```

Edit `web/Caddyfile`:

```sh
$EDITOR web/Caddyfile
```

Caddyfile requirements:

- serve the built frontend from `/srv/www`
- use `/index.html` as the frontend fallback
- handle `/api/*` with `php_fastcgi backend:9000`
- set the API root to `/var/www/backend/public`
- route API requests to `/index.php`, since Phase 1 has a single PHP entrypoint
- serve uploaded dog images from `/uploads/dogs` when that feature is added

Caddy must be able to read `backend/public/index.php`, so mount `./backend/public` into the `web` service read-only.

## 3. Add PHP runtime configuration

Put PHP configuration with the backend because it is backend-specific:

```sh
$EDITOR backend/php.ini
```

Suggested settings:

- `upload_max_filesize = 5M`
- `post_max_size = 6M`
- `memory_limit = 128M`
- `max_execution_time = 30`

Mount this file into the backend container at `/usr/local/etc/php/conf.d/zz-abqdog.ini`.

## 4. Edit the existing `compose.yml`

Do not create a new Compose file. Edit the existing one:

```sh
$EDITOR compose.yml
```

Use services named for their directories:

- `frontend`
- `backend`
- `web`

Compose requirements:

- `frontend`
  - build `./frontend`, target `deploy`
  - populate the `frontend-dist` volume from `/app/dist`
  - exit successfully so `web` can depend on it with `service_completed_successfully`
- `backend`
  - build `./backend`, target `deploy`
  - mount `sqlite-data` at `/data`
  - mount `uploaded-images` at `/uploads`
  - mount `./backend/php.ini` read-only into PHP config
  - set backend environment values such as `DATABASE_PATH`, `UPLOAD_DIR`, and `PUBLIC_UPLOAD_BASE`
- `web`
  - use `caddy:2.11.4-alpine`
  - depend on `frontend` completing successfully and `backend` starting
  - publish `8080:80`
  - mount `./web/Caddyfile` to `/etc/caddy/Caddyfile:ro`
  - mount `frontend-dist` to `/srv/www:ro`
  - mount `uploaded-images` to `/uploads:ro`
  - mount `./backend/public` to `/var/www/backend/public:ro`

Keep these named volumes:

- `frontend-dist`
- `sqlite-data`
- `uploaded-images`

## 5. Verify

```sh
docker compose up --build
```

In another terminal:

```sh
curl -I http://localhost:8080
curl http://localhost:8080/api/health
```

The Phase 1 backend placeholder should return JSON like:

```json
{"ok":true}
```

Stop services:

```sh
docker compose down
```

## Completion checklist

- `web/Caddyfile` exists; root `Caddyfile` was moved.
- `backend/php.ini` exists.
- `compose.yml` was edited in place.
- Services are named `frontend`, `backend`, and `web`.
- `web` uses `caddy:2.11.4-alpine`.
- Compose builds `frontend/Dockerfile` and `backend/Dockerfile`.
- The site responds on `http://localhost:8080`.
- The backend placeholder responds through `/api/*`.
