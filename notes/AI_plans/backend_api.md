# Backend API plan

This expands Phase 3 of `plan.md` for the current repository state.

## Current project adjustments

Phase 2 was implemented with these names and defaults, so Phase 3 should follow them:

- PHP requirement: `8.5.7` from `backend/composer.json`.
- Database helper: `AbqDog\Database` in `backend/src/Database.php`.
- Default database path: `/data/abqdog.sqlite`.
- Dogs table public description column: `description`, not `short_description`.
- Existing upload limits live in `backend/php.ini` and already set `upload_max_filesize = 5M` and `post_max_size = 6M`.

## Goals

Implement a small JSON API behind PHP-FPM with explicit, easy-to-adjust routing.

Initial public endpoints:

- `GET /api/health`
- `GET /api/dogs`
- `POST /api/submissions`

The exact route strings should be centralized in one obvious place so they can be renamed later without hunting through controller code.

## Proposed backend structure

Add these files under `backend/src/`:

```text
src/
├── Config.php
├── Http.php
├── Router.php
├── Validation.php
├── Uploads.php
└── Handlers/
    ├── HealthHandler.php
    ├── DogsHandler.php
    └── SubmissionsHandler.php
```

Keep `backend/public/index.php` as the front controller. It should only load Composer, build a route table, dispatch the request, and convert uncaught exceptions to JSON 500 responses.

## Route design

Use a tiny route table instead of hard-coded `if`/`else` blocks. This keeps routing explicit but easy to adjust.

Example shape:

```php
$routes = [
    ['GET',  '/api/health',      [HealthHandler::class, 'show']],
    ['GET',  '/api/dogs',        [DogsHandler::class, 'index']],
    ['POST', '/api/submissions', [SubmissionsHandler::class, 'create']],
];
```

`Router` responsibilities:

1. Read the method from `$_SERVER['REQUEST_METHOD']`.
2. Read the path from `parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)`.
3. Match exact method + path pairs.
4. Return a JSON `404` for no path match.
5. Return a JSON `405` for a known path with the wrong method, including an `Allow` header.
6. Invoke the matching handler.

Do not add route parameters yet. If they become necessary later, replace only `Router`, not handlers.

## Shared HTTP helpers

Create `Http.php` with small helpers such as:

- `jsonResponse(array $payload, int $status = 200, array $headers = []): void`
- `jsonError(string $message, int $status, array $details = []): void`
- `methodNotAllowed(array $allowedMethods): void`

Every response should include:

```http
Content-Type: application/json; charset=utf-8
```

Use appropriate status codes:

- `200` for successful reads.
- `201` for a successful submission.
- `400` for malformed input.
- `404` for unknown routes.
- `405` for unsupported methods on known routes.
- `413` for upload too large where PHP exposes that condition.
- `415` for unsupported image type.
- `422` for validation errors.
- `500` for unexpected server errors.

## Configuration

Create `Config.php` with environment-backed accessors:

- `databasePath()` can defer to `Database::connect()` and does not need duplication unless useful.
- `uploadDir()`: `UPLOAD_DIR`, default `/uploads/dogs`.
- `publicUploadBase()`: `PUBLIC_UPLOAD_BASE`, default `/uploads/dogs`.
- `maxUploadBytes()`: fixed `5 * 1024 * 1024` for now, or `MAX_UPLOAD_BYTES` with that default.

Normalize paths carefully:

- `UPLOAD_DIR` should be a filesystem path.
- `PUBLIC_UPLOAD_BASE` should be a URL path with no trailing slash when constructing `photo_url`.
- The API should never return `UPLOAD_DIR` or any filesystem path.

## `GET /api/health`

Return:

```json
{ "ok": true }
```

Keep this endpoint database-independent so it can tell whether PHP-FPM routing works even if the database is unavailable. A deeper database health check can be added later if needed.

## `GET /api/dogs`

Return approved dogs only, newest first. Select only public fields:

```sql
SELECT id, dog_name, description, photo_filename, neighborhood, created_at
FROM dogs
WHERE status = 'approved'
ORDER BY created_at DESC, id DESC;
```

Map each row to:

```json
{
  "id": 1,
  "dog_name": "Example",
  "description": "Short public description.",
  "photo_url": "/uploads/dogs/example.webp",
  "neighborhood": "Nob Hill",
  "created_at": "2026-06-13T00:00:00Z"
}
```

Response shape:

```json
{
  "dogs": []
}
```

Do not return `photo_filename`, `owner_name`, `owner_email`, `status`, or filesystem paths.

## `POST /api/submissions`

Accept `multipart/form-data` fields:

- `dog_name`
- `description`
- `photo`
- `owner_name`
- `owner_email`
- `neighborhood` optional

### Text validation

Implement in `Validation.php`. Suggested first-version rules:

- Trim all string fields.
- `dog_name`: required, 1-80 characters.
- `description`: required, 10-500 characters.
- `owner_name`: required, 1-120 characters.
- `owner_email`: required, valid email, max 254 characters.
- `neighborhood`: optional, max 120 characters; store `NULL` if blank.
- Reject control characters except normal whitespace.

Return validation errors as field-addressable JSON, for example:

```json
{
  "error": "Validation failed.",
  "fields": {
    "dog_name": "Dog name is required."
  }
}
```

### Upload validation and storage

Implement in `Uploads.php`.

Rules:

1. Require exactly one uploaded file under the `photo` field.
2. Reject missing, empty, partial, or multiple uploads.
3. Reject files larger than 5 MB.
4. Validate MIME type with `finfo_file`, not the client filename or `$_FILES['photo']['type']`.
5. Accept only:
   - `image/jpeg` -> `.jpg`
   - `image/png` -> `.png`
   - `image/webp` -> `.webp`
6. Generate the stored filename server-side, for example:

   ```php
   bin2hex(random_bytes(16)) . $extension
   ```

7. Ensure `UPLOAD_DIR` exists and is writable; create it if needed.
8. Store with `move_uploaded_file` only.
9. On database insert failure after moving a file, delete the uploaded file before returning an error.

Do not preserve user-provided filenames.

### Database insert

Use `AbqDog\Database::connect()` and a prepared statement:

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

Use one UTC ISO-8601 timestamp value for both `created_at` and `updated_at`.

Successful response:

```json
{
  "ok": true,
  "message": "Submission received."
}
```

Use status `201`. Do not return private fields. Returning the new database id is optional; if returned, treat it as an internal reference, not a public dog page id yet.

## Caddy and Docker updates

Add this as an explicit implementation step because uploads need shared storage and public serving.

1. In `compose.yml`, pass backend environment variables:

   ```yaml
   environment:
     DATABASE_PATH: ${DATABASE_PATH:?DATABASE_PATH}
     UPLOAD_DIR: ${UPLOAD_DIR:-/uploads/dogs}
     PUBLIC_UPLOAD_BASE: ${PUBLIC_UPLOAD_BASE:-/uploads/dogs}
   ```

2. Add a persistent uploads volume mounted into the backend at `/uploads`:

   ```yaml
   volumes:
     - sqlite-data:/data
     - uploaded-images:/uploads
   ```

3. Mount the same uploads volume into the Caddy/web service at `/uploads:ro` so public images can be served.

4. Declare the volume:

   ```yaml
   volumes:
     sqlite-data:
     uploaded-images:
   ```

5. Update `web/Caddyfile` to serve uploaded files before frontend fallback, for example:

   ```caddyfile
   handle /uploads/dogs/* {
       root * /uploads
       file_server
   }
   ```

   Keep the `/api/*` handler before the frontend fallback.

6. Make equivalent development changes in `compose.dev.yml` and `web/Caddyfile.dev` if local submitted uploads need to be visible through Caddy during development.

7. Confirm `backend/php.ini` still has compatible limits. Current values are acceptable for a 5 MB upload:

   ```ini
   upload_max_filesize = 5M
   post_max_size = 6M
   ```

## Implementation steps

1. Add `Config`, `Http`, and `Router` classes under `backend/src/`. Add Handlers directory, and stubs for the handlers.
2. Replace the temporary `backend/public/index.php` health-only response with front-controller dispatch.
3. Implement `GET /api/health`.
4. Implement `GET /api/dogs` with a public-only SELECT and `photo_url` mapping.
5. Implement `POST /api/submissions` text validation, upload validation, file storage, and pending insert.
6. Update Compose and Caddy configuration for upload storage and serving.
7. Update `.env.example` with `UPLOAD_DIR` and `PUBLIC_UPLOAD_BASE` defaults if they are not already present.
8. Update README with API smoke-test commands and the manual moderation reminder.

## Manual verification

From the repository root:

```sh
just dev
```

In another terminal:

```sh
curl -i http://localhost:8080/api/health
curl -i http://localhost:8080/api/dogs
```

Test submission with a local image:

```sh
curl -i -X POST http://localhost:8080/api/submissions \
  -F 'dog_name=Test Dog' \
  -F 'description=A friendly Albuquerque dog submitted during development.' \
  -F 'owner_name=Test Owner' \
  -F 'owner_email=test@example.com' \
  -F 'neighborhood=Nob Hill' \
  -F 'photo=@/path/to/test-image.webp'
```

Then check:

```sh
just db-shell
```

Verify the new row has `status = 'pending'` and that `GET /api/dogs` does not include it until manually approved.

Also verify negative cases:

- Missing required text field returns `422`.
- Invalid email returns `422`.
- Missing photo returns `422` or `400`.
- Oversized photo returns `413` where detectable.
- Unsupported photo MIME returns `415`.
- `GET /api/submissions` returns `405`.
- Unknown route returns `404`.

## Security and privacy notes

- Do not add CORS for version 1; the frontend and API are same-origin behind Caddy.
- Do not log owner name, owner email, or raw submitted field values.
- Do not expose pending or rejected dogs from public endpoints.
- Do not expose `owner_name`, `owner_email`, `photo_filename`, or internal paths.
- Consider adding a honeypot or rate limiting later if spam appears, but do not complicate the first API version unnecessarily.
