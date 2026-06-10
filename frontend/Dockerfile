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
