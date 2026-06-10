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

Use `pnpm` for the frontend rather than `npm`. This is preferable here because it creates a deterministic `pnpm-lock.yaml`, is fast in local and container builds, and avoids committing multiple JavaScript lockfile formats. Use these explicit versions:

- Node.js: `24.16.0`
- pnpm: `10.24.0`
- Vite/create-vite: `8.0.16`

Enable the specified pnpm version through Corepack:

```sh
corepack enable
corepack prepare pnpm@10.24.0 --activate
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

Create the frontend Dockerfile at `frontend/Dockerfile`:

```sh
cat > frontend/Dockerfile <<'DOCKERFILE'
FROM node:24.16.0-alpine3.22 AS build

ARG UID=1001
ARG GID=1001
ARG PNPM_VERSION=10.24.0

RUN addgroup -g ${GID} app \
    && adduser -D -u ${UID} -G app app \
    && mkdir -p /app \
    && chown app:app /app

WORKDIR /app

RUN corepack enable \
    && corepack prepare pnpm@${PNPM_VERSION} --activate

COPY --chown=app:app package.json pnpm-lock.yaml ./
USER app
RUN pnpm install --frozen-lockfile

COPY --chown=app:app . ./
RUN pnpm run build

FROM node:24.16.0-alpine3.22 AS runtime

ARG UID=1001
ARG GID=1001
ARG PNPM_VERSION=10.24.0

RUN addgroup -g ${GID} app \
    && adduser -D -u ${UID} -G app app \
    && mkdir -p /app \
    && chown app:app /app

WORKDIR /app

RUN corepack enable \
    && corepack prepare pnpm@${PNPM_VERSION} --activate

COPY --from=build --chown=app:app /app/package.json /app/pnpm-lock.yaml ./
COPY --from=build --chown=app:app /app/node_modules ./node_modules
COPY --from=build --chown=app:app /app/dist ./dist

USER app
EXPOSE 4173
CMD ["pnpm", "exec", "vite", "preview", "--host", "0.0.0.0", "--port", "4173"]
DOCKERFILE
```

Test the frontend Dockerfile from the repository root:

```sh
docker build -t abqdog-frontend:phase1 ./frontend
docker run --rm -p 4173:4173 abqdog-frontend:phase1
```

In a second terminal, verify that Vite preview responds:

```sh
curl -I http://localhost:4173
```

Stop the container with `Ctrl+C`.

Optionally test non-default container user ids:

```sh
docker build \
  --build-arg UID=$(id -u) \
  --build-arg GID=$(id -g) \
  -t abqdog-frontend:phase1-local-user \
  ./frontend
```

## 3. Verify frontend setup

Run frontend checks:

```sh
cd frontend
pnpm run build
cd ..
```

## Frontend completion checklist

- `frontend/` exists and builds with Vite, React, and TypeScript.
- `frontend/pnpm-lock.yaml` exists.
- `frontend/Dockerfile` exists and the image builds.
- The frontend container responds on `http://localhost:4173` when run locally.
