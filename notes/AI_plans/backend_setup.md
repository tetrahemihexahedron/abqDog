# Phase 1 backend setup

Set up the PHP backend skeleton and Docker image for albuquerque.dog.

## 1. Initialize Composer

From the repository root:

```sh
mkdir -p backend
cd backend
composer init
```

Answer the prompts interactively. Use `project` as the package type, require PHP 8.5.7, and skip dependencies unless they are needed now.

If PHP was not added during `composer init`, add it before installing:

```sh
composer require php:8.5.7 --no-update
```

Install dependencies and generate `composer.lock`:

```sh
composer install
```

Configure PSR-4 autoloading for future backend source files under `src/`:

```sh
composer dump-autoload
```

Ensure `composer.json` includes:

- package name `abqdog/backend`
- type `project`
- license `MIT`
- PHP requirement `8.5.7`
- PSR-4 autoloading from `AbqDog\\` to `src/`

Do not create empty `src/` or `migrations/` directories yet.

## 2. Add backend files

Create the public entrypoint and ignore generated dependencies:

```sh
mkdir -p public
```

Create files:

- `backend/public/index.php`: minimal JSON health response using Composer autoloading
- `backend/.gitignore`: ignores `/vendor/`
- `backend/.dockerignore`: excludes `vendor/` from the Docker build context

Return to the repository root:

```sh
cd ..
```

## 3. Add the backend Dockerfile

Create `backend/Dockerfile`.

Dockerfile requirements:

- use build args for `PHP_VERSION`, `ALPINE_VERSION`, and `COMPOSER_VERSION`
- use versioned images, not `latest`
- copy Composer from a pinned Composer image stage
- install PHP SQLite support (`pdo_sqlite`)
- use a `vendor` build stage for `composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader`
- use a final `deploy` stage
- create and run as non-root user `app`, with `UID` and `GID` build args defaulting to `1001`
- set `WORKDIR /var/www/backend`
- run `php-fpm` by default

## 4. Verify locally

From `backend/`:

```sh
composer validate
php -l public/index.php
```

From the repository root:

```sh
docker build -f backend/Dockerfile -t abqdog-backend:phase1 backend
docker run --rm abqdog-backend:phase1 php -v
docker run --rm abqdog-backend:phase1 php -m | grep -E 'PDO|pdo_sqlite'
docker run --rm abqdog-backend:phase1 id
```

## Completion checklist

- `backend/composer.json`, `backend/composer.lock`, and `backend/public/index.php` exist.
- `backend/Dockerfile` builds from the `backend/` context.
- The image has PHP SQLite support.
- The image runs as UID/GID `1001` by default.
- No empty directories or placeholder files were created.
