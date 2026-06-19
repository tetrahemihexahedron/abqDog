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

- `GET /data/health`
- `GET /data/dogs`
- `POST /data/submissions`

The exact route strings should be centralized in one obvious place so they can be renamed later without hunting through controller code.

## Proposed backend structure

Add backend source files incrementally under `backend/src/`:

```text
src/
├── Config.php
├── Http.php
├── Response.php
├── Router.php
└── Handlers/
    └── HealthHandler.php
```

Add `DogsHandler.php` when implementing `GET /data/dogs`. Add `Validation.php`, `Uploads.php`, and `SubmissionsHandler.php` only when implementing `POST /data/submissions`; do not create placeholder classes before they are needed.

Keep `backend/public/index.php` as the front controller. It should only load Composer, build a route table, read the HTTP method/path, dispatch the request, convert uncaught exceptions to JSON 500 responses, and send the final `Response`.

## Route design

Use a tiny route table instead of hard-coded `if`/`else` blocks. This keeps routing explicit but easy to adjust.

Use a path-keyed route table with methods nested under each path:

```php
$routes = [
    '/data/health' => [
        'GET' => [HealthHandler::class, 'check'],
    ],
    '/data/dogs' => [
        'GET' => [DogsHandler::class, 'getApproved'],
    ],
    '/data/submissions' => [
        'POST' => [SubmissionsHandler::class, 'create'],
    ],
];
```

More elaborate structures with route objects or builder methods are unnecessary until the API needs path parameters, middleware, or route groups.

`Router` responsibilities:

1. Accept the already-parsed HTTP method and path as arguments.
2. Match exact method + path pairs.
3. Return a JSON `404` `Response` for no path match.
4. Return a JSON `405` `Response` for a known path with the wrong method, including an `Allow` header.
5. Invoke the matching handler and return its `Response`.

`public/index.php` is responsible for reading `$_SERVER['REQUEST_METHOD']` and `parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)`, then passing those values to `Router::dispatch()`.

Do not add route parameters yet. If they become necessary later, replace only `Router`, not handlers.

## Shared HTTP helpers

Create `Response.php` as a small immutable response object containing:

- HTTP status code.
- JSON payload array.
- Additional headers.

Create `Http.php` with small helpers such as:

- `jsonResponse(array $payload, int $status = 200, array $headers = []): Response`
- `jsonError(string $message, int $status, array $details = []): Response`
- `methodNotAllowed(array $allowedMethods): Response`
- `send(Response $response): void`

Only `Http::send()` should emit headers and body. Routing and handlers should return `Response` objects so they are easy to test without subprocesses or real HTTP.

Every sent response should include:

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

- `databasePath()`: `DATABASE_PATH`, default `/data/abqdog.sqlite`; `Database::connect()` should use this accessor.
- `dogImageUploadDir()`: `DOG_IMAGE_UPLOAD_DIR`, default `/uploads/dogs`.
- `dogImageUrlBase()`: `DOG_IMAGE_URL_BASE`, default `/img/dogs`.

Normalize paths carefully:

- `DOG_IMAGE_UPLOAD_DIR` should be a filesystem path where submitted dog images are stored.
- `DOG_IMAGE_URL_BASE` should be a URL path with no trailing slash when constructing `photo_url`.
- The API should never return `DOG_IMAGE_UPLOAD_DIR` or any filesystem path.

Using “image” terminology is fine and is a little clearer than “upload” for code that later serves approved dog photos. The main caveat is to remember these files originate from user uploads, so validation and moderation rules still need to treat them as untrusted input.

## `GET /data/health`

Return:

```json
{ "ok": true }
```

Keep this endpoint database-independent so it can tell whether PHP-FPM routing works even if the database is unavailable. A deeper database health check can be added later if needed.

## `GET /data/dogs`

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
  "photo_url": "/img/dogs/example.webp",
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

## `POST /data/submissions`

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

7. Ensure `DOG_IMAGE_UPLOAD_DIR` exists and is writable; create it if needed.
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
     DOG_IMAGE_UPLOAD_DIR: ${DOG_IMAGE_UPLOAD_DIR:-/uploads/dogs}
     DOG_IMAGE_URL_BASE: ${DOG_IMAGE_URL_BASE:-/img/dogs}
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
   handle /img/dogs/* {
       uri strip_prefix /img/dogs
       root * /uploads/dogs
       file_server
   }
   ```

   Keep the `/data/*` handler before the frontend fallback.

6. Make equivalent development changes in `compose.dev.yml` and `web/Caddyfile.dev` if local submitted uploads need to be visible through Caddy during development.

7. Confirm `backend/php.ini` still has compatible limits. Current values are acceptable for a 5 MB upload:

   ```ini
   upload_max_filesize = 5M
   post_max_size = 6M
   ```

## Implementation steps

1. Done: confirmed no Caddy changes were needed to test `GET /data/health`; existing `/data/*` handlers in `web/Caddyfile` and `web/Caddyfile.dev` already route API requests to PHP-FPM.
2. Done: added `Config`, `Http`, `Response`, and `Router` classes under `backend/src/`. `Config` now centralizes `DATABASE_PATH`, `DOG_IMAGE_UPLOAD_DIR`, and `DOG_IMAGE_URL_BASE`; `Database::connect()` uses `Config::databasePath()`.
3. Done: added `backend/src/Handlers/HealthHandler.php`; no placeholder handlers or submission-only classes were created.
4. Done: replaced the temporary `backend/public/index.php` health-only response with front-controller dispatch.
5. Done: implemented `GET /data/health` via the route table. Routing and handlers now return `Response` objects; only `Http::send()` emits headers/body.
6. Done: implemented `GET /data/dogs` with a public-only SELECT and `photo_url` mapping. Added `DogsHandler.php`.
7. Implement `POST /data/submissions` in substeps:
   - Done: added `SubmissionsHandler.php` with an initial handler shape for multipart submissions.
   - Done: registered `POST /data/submissions` in the route table.
   - Add `Validation.php` and implement text-field trimming and validation rules.
   - Add `Uploads.php` and implement photo upload validation, MIME checks, filename generation, and file storage.
   - Complete `SubmissionsHandler.php` with validation, upload handling, pending database insert, cleanup on insert failure, and `201` success response.
8. Update Compose and Caddy configuration for upload storage and serving.
9. Done: update `.env.example` with `DOG_IMAGE_UPLOAD_DIR` and `DOG_IMAGE_URL_BASE` defaults.
10. Update README with API smoke-test commands and the manual moderation reminder.

## Manual verification

From the repository root:

```sh
just dev
```

In another terminal:

```sh
curl -i http://localhost:8080/data/health
curl -i -X POST http://localhost:8080/data/health  # should return 405
curl -i http://localhost:8080/data/missing         # should return 404
curl -i http://localhost:8080/data/dogs            # after GET /data/dogs is implemented
```

Test submission with a local image:

```sh
curl -i -X POST http://localhost:8080/data/submissions \
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

Verify the new row has `status = 'pending'` and that `GET /data/dogs` does not include it until manually approved.

Also verify negative cases:

- Missing required text field returns `422`.
- Invalid email returns `422`.
- Missing photo returns `422` or `400`.
- Oversized photo returns `413` where detectable.
- Unsupported photo MIME returns `415`.
- `GET /data/submissions` returns `405`.
- Unknown route returns `404`.

## Security and privacy notes

- Do not add CORS for version 1; the frontend and API are same-origin behind Caddy.
- Do not log owner name, owner email, or raw submitted field values.
- Do not expose pending or rejected dogs from public endpoints.
- Do not expose `owner_name`, `owner_email`, `photo_filename`, or internal paths.
- Consider adding a honeypot or rate limiting later if spam appears, but do not complicate the first API version unnecessarily.
