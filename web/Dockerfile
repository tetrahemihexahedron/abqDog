ARG NODE_VERSION=24.16.0
ARG ALPINE_VERSION=3.24
ARG PNPM_VERSION=11.5.2
ARG COREPACK_VERSION=0.35.0
ARG CADDY_VERSION=2.11.4

FROM node:${NODE_VERSION}-alpine${ALPINE_VERSION} AS frontend-build

ARG PNPM_VERSION
ARG COREPACK_VERSION

WORKDIR /frontend

RUN npm install --global corepack@${COREPACK_VERSION} \
	&& corepack enable \
	&& corepack prepare pnpm@${PNPM_VERSION} --activate

COPY frontend/package.json frontend/pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY frontend/ ./
RUN pnpm run build

FROM caddy:${CADDY_VERSION}-alpine AS deploy

COPY web/Caddyfile /etc/caddy/Caddyfile
COPY --from=frontend-build /frontend/dist /srv/www
