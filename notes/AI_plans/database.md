# Database plan

This expands phase 2 of `plan.md` for the first version of albuquerque.dog.

## Goals

Use one small SQLite database to store submitted dog records. The database should be simple to inspect manually, easy to back up, and safe for use by the PHP API through PDO prepared statements.

Default database path:

```text
/data/abqdog.sqlite
```

The backend should read the path from `DATABASE_PATH` so development, test, and production can use different database files.

## Schema

Version 1 can use a single table, `dogs`, for both public dog listings and private submission metadata.

```sql
CREATE TABLE dogs (
  id INTEGER PRIMARY KEY,
  dog_name TEXT NOT NULL,
  description TEXT NOT NULL,
  photo_filename TEXT NOT NULL,
  owner_name TEXT NOT NULL,
  owner_email TEXT NOT NULL,
  neighborhood TEXT,
  status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
  created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now')),
  updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%SZ', 'now'))
);

CREATE INDEX idx_dogs_status ON dogs(status);
CREATE INDEX idx_dogs_created_at ON dogs(created_at);
```

## Field notes

- `dog_name`: public, required.
- `description`: public, required; keep validation limits in PHP rather than relying only on SQL.
- `photo_filename`: public only after converting to a URL; store a filename, not an absolute path.
- `owner_name`: private, required; must not be returned by public endpoints.
- `owner_email`: private, required; must not be returned by public endpoints.
- `neighborhood`: public, optional.
- `status`: moderation state. New submissions should be `pending`; only `approved` dogs appear in `GET /api/dogs`.
- `created_at` and `updated_at`: ISO-8601 UTC text timestamps.

## Migrations

Keep migrations as plain numbered SQL files under `backend/migrations/`:

```text
001_create_dogs_table.sql
002_future_change.sql
```

For version 1, applying migrations manually with `sqlite3` is acceptable. Automated migration tracking can be added later if schema changes become frequent.

Example manual initialization:

```sh
sqlite3 "$DATABASE_PATH" < migrations/001_create_dogs_table.sql
```

The command should be run in the backend container.

## Steps to perform

1. Done: create the initial migration file at `backend/migrations/001_create_dogs_table.sql` using the schema above.
2. Done: define `DATABASE_PATH` once for the whole application in the root `.env` file, ignored by git:

   ```text
   DATABASE_PATH=/data/abqdog.sqlite
   ```

   `compose.yml` passes this value into the backend container.
3. Done: mount a persistent named volume at `/data` for the backend container.
4. Done: ensure the backend image includes PDO SQLite and the `sqlite3` CLI, and that `/data` is writable by the application user.
5. Done: initialize the database by applying `001_create_dogs_table.sql` to `DATABASE_PATH` from inside the backend container.
6. Done: apply WAL mode during initialization and verify that the `dogs` table exists.
7. Done: create development-only seed data at `backend/dev-data/seed.sql`, with fake pet squash entries and matching tracked images in `backend/dev-data/uploads/dogs/`. It includes `approved`, `pending`, and `rejected` rows.
8. Done: load the test data into the development database only. Do not seed production with fake dogs unless intentionally needed.
9. Done: add `backend/src/Database.php`, a small PHP database connection helper using PDO. It reads `DATABASE_PATH`, defaults to `/data/abqdog.sqlite`, connects with a `sqlite:` DSN, sets PDO error and fetch modes, and runs `PRAGMA foreign_keys = ON` and `PRAGMA busy_timeout = 5000` for every connection.
10. Done: test the PDO connection from inside the backend container with `SELECT COUNT(*) FROM dogs`.
11. Done: document the exact migration, seed, and PDO verification commands in the root README.

## Operational notes

- Back up the SQLite file and uploaded images together; dog records depend on image filenames.
- Keep the database file outside the web root.
- Ensure the PHP-FPM process can read and write the database file and its parent directory.
- Prefer running manual database commands in the backend container or an equivalent maintenance container so commands operate on the same mounted database used by the application.
- Consider setting `PRAGMA foreign_keys = ON` on every PDO connection, even though version 1 has no foreign keys.
- Consider using SQLite WAL mode for better behavior under concurrent reads and writes:

```sql
PRAGMA journal_mode = WAL;
```

This can be applied during initialization if it works well with the chosen Docker volume setup.

## Usage notes

### API query requirements

Public dog listings must explicitly select only public fields:

```sql
SELECT id, dog_name, description, photo_filename, neighborhood, created_at
FROM dogs
WHERE status = 'approved'
ORDER BY created_at DESC, id DESC;
```

The API should derive `photo_url` from `PUBLIC_UPLOAD_BASE` plus `photo_filename`. It should never expose filesystem paths or private owner fields.

Submissions should be inserted with prepared statements and `status = 'pending'`:

```sql
INSERT INTO dogs (
  dog_name,
  description,
  photo_filename,
  owner_name,
  owner_email,
  neighborhood,
  status,
  created_at,
  updated_at
) VALUES (
  :dog_name,
  :description,
  :photo_filename,
  :owner_name,
  :owner_email,
  :neighborhood,
  'pending',
  :created_at,
  :updated_at
);
```

### Moderation workflow

Do not build an admin interface for the first version. Moderate manually with SQLite commands:

```sql
UPDATE dogs
SET status = 'approved', updated_at = strftime('%Y-%m-%dT%H:%M:%SZ', 'now')
WHERE id = ?;

UPDATE dogs
SET status = 'rejected', updated_at = strftime('%Y-%m-%dT%H:%M:%SZ', 'now')
WHERE id = ?;
```
