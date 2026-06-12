# Phase 1 Compose and web setup

This records the Docker Compose/web setup completed after frontend and backend initialization.

## Files changed

- `web/Dockerfile`
- `web/Caddyfile`
- `web/Caddyfile.dev`
- `backend/Dockerfile`
- `backend/php.ini`
- `compose.yml`
- `compose.dev.yml`
- `README.md`

## Current setup

Production uses `compose.yml`:

- `backend`
  - builds `./backend`, target `deploy`
  - installs Composer dependencies in a build stage
  - copies `backend/php.ini` into the image
  - runs PHP-FPM as non-root user `app`
  - uses `init: true` and `restart: unless-stopped`
- `web`
  - builds from repository root with `web/Dockerfile`
  - builds frontend assets in a Node/pnpm stage
  - copies built frontend assets into a pinned Caddy image
  - serves frontend routes from `/srv/www`
  - routes `/api/*` to `php_fastcgi backend:9000`
  - publishes `8080:80`
  - uses `init: true` and `restart: unless-stopped`
- default network is explicitly named `abqdog`

Development uses `compose.yml` plus `compose.dev.yml`:

- adds a `frontend` service running the Vite dev server
- bind-mounts frontend and backend source directories
- uses named volumes for `node_modules` and backend `vendor`
- replaces production Caddy build with `caddy:2.11.4-alpine`
- mounts `web/Caddyfile.dev`
- disables inherited restart policies for dev backend/web

## Verification commands run

```sh
docker compose config
docker compose -f compose.yml -f compose.dev.yml config
docker compose build
docker compose up -d
curl http://localhost:8080/
curl http://localhost:8080/api/
docker compose down
```

Verified results:

- production images build successfully
- frontend responds on `http://localhost:8080`
- `/api/` returns `{"ok":true}` through Caddy/PHP-FPM
- services stop cleanly with `docker compose down`
