# Phase 1 frontend setup

Steps performed to set up the frontend environment and perform a temporary frontend-only container test.

## Versions used

- Node.js: `24.16.0`
- Alpine: `3.24`
- pnpm: `11.5.2`
- Corepack: `0.35.0`
- create-vite: `9.0.7`
- Vite: `8.0.16`
- Caddy: `caddy:2.10.2-alpine`

## Commands run

From the repository root:

```sh
cd /workspaces/abqDog
```

Install and activate pinned pnpm through Corepack:

```sh
npm install --global corepack@0.35.0
corepack enable
corepack prepare pnpm@11.5.2 --activate
pnpm --version
```

Create the Vite React TypeScript frontend:

```sh
pnpm dlx create-vite@9.0.7 frontend --template react-ts
cd frontend
pnpm install
pnpm add -D vite@8.0.16
```

Pin `vite` exactly to `8.0.16` in `frontend/package.json`, then update the lockfile:

```sh
pnpm install --lockfile-only
pnpm list --depth 0
cd ..
```

Files created/edited:

- `frontend/Dockerfile`
- `frontend/.dockerignore`
- root `Caddyfile`
- root `compose.yml`

Build and test the frontend image:

```sh
docker build -t abqdog-frontend-build:phase1 ./frontend
```

Test the temporary frontend-only Compose setup:

```sh
docker compose -f compose.yml down -v
docker compose -f compose.yml up --build -d
curl -I http://localhost:8080
docker compose -f compose.yml down
```

Run the local frontend build check:

```sh
cd frontend
pnpm run build
cd ..
```

## Current setup

- `frontend/Dockerfile` has a `build` stage that installs dependencies and builds `/app/dist`.
- `frontend/Dockerfile` has a `deploy` stage that creates an Alpine `app` user with `addgroup`/`adduser`, creates `/app/dist`, sets ownership, and copies built assets into `/app/dist`.
- The deploy image has no long-running command; `compose.yml` runs `command: ["true"]` so Docker initializes the named volume from `/app/dist` and the service exits successfully.
- Caddy runs as a separate service and serves the `frontend-dist` volume from `/srv/www`.

## Potential issues

- The setup relies on Docker named-volume initialization. If `frontend-dist` already exists, new image assets may not replace old files unless the volume is removed.
- Use `docker compose -f compose.yml down -v` when a fresh frontend volume is needed.
- The temporary `compose.yml` is frontend-only and will likely be replaced when backend services are added.
- The deploy image uses Node only to provide files for volume initialization; it is not the final production serving image.

## Potential improvements

- Replace the temporary frontend-only Compose file with the main full-stack Compose file later.
- Consider using a dedicated asset-copy helper image or a Caddy image with copied static assets for production.
- Add Docker build args for `UID` and `GID` in Compose if local user IDs need to be customized.
- Add CI checks for `pnpm run build`, Docker build, and Compose smoke test.

## Completion checklist

- `frontend/` exists and builds with Vite, React, and TypeScript.
- `frontend/pnpm-lock.yaml` exists.
- `frontend/Dockerfile` exists and builds.
- `frontend/.dockerignore` exists.
- Root `Caddyfile` and `compose.yml` exist for temporary frontend-only testing.
- Caddy serves the built frontend at `http://localhost:8080` during the Compose smoke test.
