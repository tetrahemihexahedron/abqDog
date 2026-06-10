# Phase 1 frontend setup

These instructions set up the Vite React TypeScript frontend and its Docker image for albuquerque.dog.

## 0. Start from the repository root

Run all commands from the project root:

```sh
cd /workspaces/abqDog
```

Optional sanity check:

```sh
pwd
find . -maxdepth 2 -type f | sort
```

## 1. Create the Vite React TypeScript frontend

Use `pnpm` for the frontend rather than `npm`. Use these explicit versions:

- Node.js: `24.16.0`
- pnpm: `11.5.2`
- Corepack: `0.35.0`
- Vite/create-vite: `8.0.16`

Install pnpm through Corepack:

```sh
# update corepack to a pinned version; see https://pnpm.io/installation#using-corepack
npm install --global corepack@0.35.0
corepack enable
corepack prepare pnpm@11.5.2 --activate
pnpm --version
```

Create a Vite React TypeScript app in `frontend/`:

```sh
pnpm dlx create-vite@8.0.16 frontend --template react-ts
```

Move into the frontend directory and install dependencies:

```sh
cd frontend
pnpm install
```

Keep all packages installed by the Vite template for now, including any linting packages and generated scripts. Do not remove template packages in Phase 1. Also do not add React Router, UI frameworks, form libraries, or validation libraries in Phase 1.

Check the generated dependencies:

```sh
pnpm list --depth 0
```

Expected core packages include:

- `react`
- `react-dom`
- `typescript`
- `vite`
- `@vitejs/plugin-react`

Return to the repository root:

```sh
cd ..
```

## 2. Create and test the frontend Dockerfile

Create the frontend Dockerfile at `frontend/Dockerfile`.

For now, the Dockerfile should only build the frontend assets. The build stage may run as `root`: it installs the JavaScript dependencies and produces static files in `dist/`. The runtime web server lives outside this Dockerfile, in a separate Caddy service.

```sh
cat > frontend/Dockerfile <<'DOCKERFILE'
FROM node:24.16.0-alpine3.22 AS build

ARG PNPM_VERSION=11.5.2
ARG COREPACK_VERSION=0.35.0

WORKDIR /app

RUN npm install --global corepack@${COREPACK_VERSION} \
    && corepack enable \
    && corepack prepare pnpm@${PNPM_VERSION} --activate

COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY . ./
RUN pnpm run build
DOCKERFILE
```

Test that the frontend Dockerfile builds from the repository root:

```sh
docker build -t abqdog-frontend-build:phase1 ./frontend
```

## 3. Create temporary frontend Caddy and Compose files

Create a temporary root-level Caddyfile for serving the built frontend:

```sh
cat > Caddyfile <<'CADDY'
:80 {
	root * /srv/www
	try_files {path} /index.html
	file_server
}
CADDY
```

Create a temporary root-level `compose.yml` with two services: one service builds the frontend assets and copies them into a shared volume, and one Caddy service serves that volume.

Use this additional explicit image version:

- Caddy image: `caddy:2.10.2-alpine`

```sh
cat > compose.yml <<'YAML'
services:
  frontend:
    build:
      context: ./frontend
    volumes:
      - frontend-dist:/dist
    command: ["sh", "-c", "rm -rf /dist/* && cp -r /app/dist/. /dist"]

  caddy:
    image: caddy:2.10.2-alpine
    depends_on:
      frontend:
        condition: service_completed_successfully
    ports:
      - "8080:80"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - frontend-dist:/srv/www:ro

volumes:
  frontend-dist:
YAML
```

Test the temporary Compose setup:

```sh
docker compose -f compose.yml up --build
```

In a second terminal, verify that Caddy serves the built frontend:

```sh
curl -I http://localhost:8080
```

Stop the Compose services:

```sh
docker compose -f compose.yml down
```

## 4. Verify frontend setup

Run frontend checks:

```sh
cd frontend
pnpm run build
cd ..
```

## Frontend completion checklist

- `frontend/` exists and builds with Vite, React, and TypeScript.
- `frontend/pnpm-lock.yaml` exists.
- `frontend/Dockerfile` exists and the build image builds.
- Temporary root-level `Caddyfile` and `compose.yml` files exist for frontend-only container testing.
- The temporary Compose setup serves the built static frontend with Caddy on `http://localhost:8080` when run locally.
