# Phase 1 Compose and web setup

This records the Compose/web setup completed after frontend and backend initialization.

## Files changed

From the repository root:

```sh
mkdir -p web
git mv Caddyfile web/Caddyfile || mv Caddyfile web/Caddyfile
```

Created/updated:

- `web/Caddyfile`
- `backend/php.ini`
- `compose.yml`

## Current setup

`web/Caddyfile`:

- serves frontend assets from `/srv/www`
- falls back to `/index.html` for frontend routes
- routes `/api/*` to `php_fastcgi backend:9000`
- rewrites API requests to `/index.php`, because Phase 1 has a single PHP entrypoint

`backend/php.ini` contains basic backend PHP runtime limits:

- `upload_max_filesize = 5M`
- `post_max_size = 6M`
- `memory_limit = 128M`
- `max_execution_time = 30`

`compose.yml` uses these services:

- `frontend`
  - builds `./frontend`, target `deploy`
  - mounts `frontend-dist` at `/app/dist`
  - exits with `command: ["true"]`
- `backend`
  - builds `./backend`, target `deploy`
  - mounts `./backend/php.ini` into PHP config
- `web`
  - uses `caddy:2.11.4-alpine`
  - depends on `frontend` completing and `backend` starting
  - publishes `8080:80`
  - mounts `web/Caddyfile`, `frontend-dist`, and `backend/public`

Only the `frontend-dist` named volume is currently used. Database and upload settings/volumes were intentionally left out until those features exist.

## Verification commands run

```sh
docker compose config
docker compose up --build -d
curl -I http://localhost:8080
curl http://localhost:8080/api/health
docker compose down
```

Verified results:

- frontend responds on `http://localhost:8080`
- `/api/health` returns `{"ok":true}`
- services stop cleanly with `docker compose down`

## Possible issues / improvements

- The `frontend` service is being used as a one-shot asset-population container. A later production setup might instead copy built assets into a dedicated Caddy image or use a shared build stage.
- Caddy needs `./backend/public` mounted so it can resolve the PHP entrypoint for FastCGI. This is acceptable for now, but should be revisited if backend routing changes.
- Database and upload volumes/env vars still need to be added when those backend features are implemented.
- `php.ini` is mounted from `backend/` because it is backend-specific; keep it there unless shared PHP configuration is later needed.
