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

Create the backend directories:

```sh
mkdir -p backend/public backend/src backend/migrations
```

Create an initial Composer project file for the backend:

```sh
cd backend
composer init \
  --name="abqdog/backend" \
  --description="Small PHP backend for albuquerque.dog" \
  --type="project" \
  --license="proprietary" \
  --require="php:^8.3" \
  --no-interaction
```

Install Composer dependencies and generate `composer.lock`:

```sh
composer install
```

Add Composer autoloading for backend source files:

```sh
composer config autoload.psr-4 'AbqDog\\' src/
composer dump-autoload
```

Return to the repository root:

```sh
cd ..
```

Create a minimal backend entrypoint:

```sh
cat > backend/public/index.php <<'PHP'
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');

echo json_encode(['ok' => true]);
PHP
```

Create placeholder backend source files so the planned structure is present:

```sh
touch \
  backend/src/db.php \
  backend/src/dogs.php \
  backend/src/submissions.php \
  backend/src/validation.php \
  backend/src/response.php
```

Create a placeholder migration file:

```sh
cat > backend/migrations/001_initial_schema.sql <<'SQL'
-- Initial schema will be added in Phase 2.
SQL
```

## 2. Create and test the backend Dockerfile

Use explicit image versions:

- PHP FPM image: `php:8.3.26-fpm-alpine3.22`
- Composer image: `composer:2.8.12`

Create the backend Dockerfile at the repository root:

```sh
cat > Dockerfile.backend <<'DOCKERFILE'
FROM php:8.3.26-fpm-alpine3.22

RUN apk add --no-cache sqlite-dev \
    && docker-php-ext-install pdo pdo_sqlite

COPY --from=composer:2.8.12 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

COPY backend/ ./

CMD ["php-fpm"]
DOCKERFILE
```

Test the backend Dockerfile from the repository root:

```sh
docker build -f Dockerfile.backend -t abqdog-backend:phase1 .
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

- `backend/` exists with `public/`, `src/`, `migrations/`, `composer.json`, and `composer.lock`.
- `backend/public/index.php` returns the Phase 1 placeholder JSON response.
- Composer autoloading is configured for `AbqDog\\` classes under `backend/src/`.
- `Dockerfile.backend` exists and the backend image builds.
- Composer is installed in the backend Docker image and used for backend autoloading.
