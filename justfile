compose := "docker compose -f compose.yml -f compose.dev.yml"

# List available commands
_default:
    just --list

# Create .env from .env.example if it does not already exist
env-init:
    test -f .env || cp .env.example .env

# Start the development environment
dev: env-init
    {{compose}} up --build

# Stop the development environment
down:
    {{compose}} down

# Initialize the development database schema
db-init: env-init
    {{compose}} run --rm backend sh -lc 'sqlite3 "$DATABASE_PATH" < migrations/001_create_dogs_table.sql'

# Load development-only fake pet squash seed data
db-seed: env-init
    {{compose}} run --rm backend sh -lc 'sqlite3 "$DATABASE_PATH" < dev-data/seed.sql'

# Reset the development database and load seed data
db-reset: env-init
    {{compose}} down
    docker volume rm abqdog_sqlite-data || true
    {{compose}} run --rm backend sh -lc 'sqlite3 "$DATABASE_PATH" < migrations/001_create_dogs_table.sql && sqlite3 "$DATABASE_PATH" < dev-data/seed.sql'

# Open a sqlite shell for the development database
db-shell: env-init
    {{compose}} run --rm backend sh -lc 'sqlite3 "$DATABASE_PATH"'

# Verify the backend PDO database connection
db-check: env-init
    {{compose}} run --rm backend php -r 'require "vendor/autoload.php"; echo AbqDog\Database::connect()->query("SELECT COUNT(*) FROM dogs")->fetchColumn() . PHP_EOL;'
