ARG NODE_VERSION=24.16.0
ARG ALPINE_VERSION=3.24

FROM node:${NODE_VERSION}-alpine${ALPINE_VERSION} AS base

ARG PNPM_VERSION=11.5.2
ARG COREPACK_VERSION=0.35.0

WORKDIR /app

RUN npm install --global corepack@${COREPACK_VERSION} \
	&& corepack enable \
	&& corepack prepare pnpm@${PNPM_VERSION} --activate

FROM base AS deps

COPY package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

FROM deps AS build

COPY . ./
RUN pnpm run build

FROM deps AS dev

ARG UID=1001
ARG GID=1001

RUN addgroup -g ${GID} app \
	&& adduser -D -u ${UID} -G app app \
	&& chown -R app:app /app

COPY --chown=app:app . ./

USER app

CMD ["pnpm", "dev", "--host", "0.0.0.0"]
