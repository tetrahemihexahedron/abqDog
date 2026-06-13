# albuquerque.dog

## Local development with Docker Compose

Run the development containers with the base Compose file plus the development override:

```sh
docker compose -f compose.yml -f compose.dev.yml up --build
```

Then open <http://localhost:8080>.

The development setup runs the Vite frontend dev server behind Caddy and bind-mounts the frontend and backend source directories for local editing. To stop the containers, press `Ctrl+C`, or run:

```sh
docker compose -f compose.yml -f compose.dev.yml down
```

## Development database

Create the local `.env` file at the repository root from the sample:

```sh
cp .env.example .env
```

Edit `.env` if local values need to differ from the defaults.

Initialize the SQLite database inside the backend container:

```sh
docker compose -f compose.yml -f compose.dev.yml run --rm backend \
  sh -lc 'sqlite3 "$DATABASE_PATH" < migrations/001_create_dogs_table.sql'
```

Load development-only fake pet squash seed data:

```sh
docker compose -f compose.yml -f compose.dev.yml run --rm backend \
  sh -lc 'sqlite3 "$DATABASE_PATH" < dev-data/seed.sql'
```

Verify the database connection through PDO:

```sh
docker compose -f compose.yml -f compose.dev.yml run --rm backend \
  php -r 'require "vendor/autoload.php"; echo AbqDog\Database::connect()->query("SELECT COUNT(*) FROM dogs")->fetchColumn() . PHP_EOL;'
```
