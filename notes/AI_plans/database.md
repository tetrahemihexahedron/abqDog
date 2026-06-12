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
001_initial_schema.sql
002_future_change.sql
```

For version 1, applying migrations manually with `sqlite3` is acceptable. Automated migration tracking can be added later if schema changes become frequent.

Example manual initialization:

```sh
sqlite3 /data/abqdog.sqlite < backend/migrations/001_initial_schema.sql
```

The command should be run in the backend container.

## Steps to perform

1. Create the initial migration file at `backend/migrations/001_initial_schema.sql` using the schema above.
2. Decide where `DATABASE_PATH` is set for each environment. For now, all environments can use the default value:

   ```text
   DATABASE_PATH=/data/abqdog.sqlite
   ```

   This is the first required application environment variable. It should eventually be set explicitly in Docker Compose for local development and production, and can be read with a default fallback in PHP.
3. Ensure the backend container has a persistent writable mount at `/data` and that the long-running PHP-FPM user can read and write the database file and parent directory.
4. Ensure the backend image or maintenance image includes the `sqlite3` CLI if migrations will be applied manually from inside the container. PHP also needs PDO SQLite enabled.
5. Initialize the database by applying `001_initial_schema.sql` to `DATABASE_PATH`.
6. Optionally apply SQLite settings during initialization, such as WAL mode, after confirming they work with the selected Docker volume setup.
7. Create a small seed or test data SQL file for local development. Include at least:
   - one `approved` dog for testing public listing behavior;
   - one `pending` dog to confirm pending submissions are hidden;
   - one `rejected` dog to confirm rejected submissions are hidden.
8. Load the test data into a development database only. Do not seed production with fake dogs unless intentionally needed.
9. Add a small PHP database connection helper using PDO. It should:
   - read `DATABASE_PATH` from the environment, defaulting to `/data/abqdog.sqlite`;
   - connect with `sqlite:` DSN;
   - set `PDO::ATTR_ERRMODE` to `PDO::ERRMODE_EXCEPTION`;
   - set `PDO::ATTR_DEFAULT_FETCH_MODE` to `PDO::FETCH_ASSOC`;
   - run `PRAGMA foreign_keys = ON` for every connection.
10. Test the PDO connection from inside the backend container by running a small PHP script or endpoint that queries the database, for example `SELECT COUNT(*) FROM dogs`.
11. Test the public query logic against the seed data and confirm only approved dogs are returned and private fields are excluded.
12. Document the exact migration and seed commands in the backend or root README once the Docker Compose service names and volume paths are finalized.

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
