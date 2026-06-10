# Phase 1 backend setup

These instructions set up the PHP backend skeleton and its Docker image for albuquerque.dog.

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

## 1. Create the backend PHP application skeleton

Create the backend application directory and initialize Composer interactively:

```sh
mkdir -p backend
cd backend
composer init
```

Answer Composer's prompts for the backend package. Use `project` as the package type, require PHP 8.3 or newer, and skip dependencies unless you know they are needed now.

Install Composer dependencies and generate `composer.lock`:

```sh
composer install
```

Add Composer autoloading for future backend source files:

```sh
composer config autoload.psr-4 'AbqDog\\' src/
composer dump-autoload
```

Create the public directory and a minimal backend entrypoint:

```sh
mkdir -p public
cat > public/index.php <<'PHP'
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

echo json_encode(['ok' => true]);
PHP
```

Return to the repository root:

```sh
cd ..
```

Do not create empty directories or placeholder files. Add directories such as `src/` or `migrations/` only when they contain real source or migration files.

## 2. Create and test the backend Dockerfile

Use explicit versions, following the same conventions as `frontend/Dockerfile`: versioned base images, version build args, a multi-stage build, and a non-root user in the final stage.

Create the backend Dockerfile at `backend/Dockerfile`:

```sh
cat > backend/Dockerfile <<'DOCKERFILE'
ARG PHP_VERSION=8.3.26
ARG ALPINE_VERSION=3.22
ARG COMPOSER_VERSION=2.8.12

FROM composer:${COMPOSER_VERSION} AS composer-bin
FROM php:${PHP_VERSION}-fpm-alpine${ALPINE_VERSION} AS php-base

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS sqlite-dev \
    && docker-php-ext-install pdo pdo_sqlite \
    && apk del .build-deps \
    && apk add --no-cache sqlite-libs

FROM php-base AS vendor

COPY --from=composer-bin /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

FROM php-base AS deploy

ARG UID=1001
ARG GID=1001

RUN addgroup -g ${GID} app \
    && adduser -D -u ${UID} -G app app \
    && mkdir -p /var/www/backend \
    && chown -R app:app /var/www/backend

WORKDIR /var/www/backend

COPY --from=vendor --chown=app:app /app/vendor ./vendor
COPY --chown=app:app . ./

USER app

CMD ["php-fpm"]
DOCKERFILE
```

Test the backend Dockerfile from the repository root, using `backend/` as the build context:

```sh
docker build -f backend/Dockerfile -t abqdog-backend:phase1 backend
docker run --rm abqdog-backend:phase1 php -v
docker run --rm abqdog-backend:phase1 php -m | grep -E 'PDO|pdo_sqlite'
```

## 3. Verify backend setup

Run backend checks:

```sh
cd backend
composer validate
php -l public/index.php
cd ..
```

## Backend completion checklist

- `backend/composer.json`, `backend/composer.lock`, and `backend/public/index.php` exist.
- `backend/public/index.php` returns the Phase 1 placeholder JSON response.
- Composer autoloading is configured for `AbqDog\\` classes under `backend/src/` for future source files.
- `backend/Dockerfile` exists and the backend image builds from the `backend/` context.
- The backend image installs PHP SQLite support and runs `php-fpm` as a non-root user.
