# Backend submission endpoint plan

This document expands step 7 of `backend_api.md`: implement `POST /data/submissions`.

## Current status

Done:

- Added `backend/src/Handlers/SubmissionsHandler.php`.
- Registered `POST /data/submissions` in the route table in `backend/public/index.php`.
- The initial handler method is `SubmissionsHandler::create()` and currently exists as a placeholder until the remaining substeps are implemented.

## Endpoint contract

Route:

```php
'/data/submissions' => [
    'POST' => [SubmissionsHandler::class, 'create'],
],
```

Accept `multipart/form-data` fields:

- `dog_name`
- `description`
- `photo`
- `owner_name`
- `owner_email`
- `neighborhood` optional

Successful response:

```json
{
  "ok": true,
  "message": "Submission received."
}
```

Use status `201`. Do not return owner name, owner email, uploaded filesystem paths, or moderation status. Returning the new database id is optional; if returned, treat it as an internal reference rather than a public dog page id.

## Implementation order

### 1. Add pending database insert

First complete the happy-path insert in `SubmissionsHandler::create()` using simple trusted placeholder values for fields that will later come from validation and upload storage. This makes it possible to verify database wiring before adding validation complexity.

Use `AbqDog\Database::connect()` and a prepared statement. Insert pending rows only:

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

Suggested handler method signature:

```php
public static function create(): Response
```

Optional helper method signatures if the handler starts to get crowded:

```php
/** @param array<string, string|null> $submission */
private static function insertPendingSubmission(array $submission, string $photoFilename): void

private static function utcTimestamp(): string
```

Keep the route response shape stable while implementing later substeps.

### 2. Add text-field validation

Add `backend/src/Validation.php` after the insert path is in place. Keep text validation independent from uploads so it can be tested without file fixtures.

Rules:

- Trim all string fields.
- `dog_name`: required, 1-80 characters.
- `description`: required, 10-500 characters.
- `owner_name`: required, 1-120 characters.
- `owner_email`: required, valid email, max 254 characters.
- `neighborhood`: optional, max 120 characters; store `NULL` if blank.
- Reject control characters except normal whitespace.

Suggested method signatures:

```php
/**
 * @param array<string, mixed> $input
 * @return array{values: array<string, string|null>, errors: array<string, string>}
 */
public static function validateSubmissionText(array $input): array

public static function hasDisallowedControlCharacters(string $value): bool
```

Return validation errors as field-addressable JSON, for example:

```json
{
  "error": "Validation failed.",
  "fields": {
    "dog_name": "Dog name is required."
  }
}
```

Use status `422` for text validation failures.

### 3. Add upload validation and file storage

Add `backend/src/Uploads.php` after text validation is working. Keep upload validation and storage in one small class because the validation result determines the generated stored filename.

Rules:

1. Require exactly one uploaded file under the `photo` field.
2. Reject missing, empty, partial, or multiple uploads.
3. Reject files larger than 5 MB.
4. Validate MIME type with `finfo_file`, not the client filename or `$_FILES['photo']['type']`.
5. Accept only:
   - `image/jpeg` -> `.jpg`
   - `image/png` -> `.png`
   - `image/webp` -> `.webp`
6. Generate the stored filename server-side, for example `bin2hex(random_bytes(16)) . $extension`.
7. Ensure `Config::dogImageUploadDir()` exists and is writable; create it if needed.
8. Store with `move_uploaded_file` only.
9. Do not preserve user-provided filenames.

Suggested method signatures:

```php
/**
 * @param array<string, mixed> $files
 * @return array{filename?: string, path?: string, error?: string, status?: int}
 */
public static function storeDogPhoto(array $files): array

public static function ensureUploadDirectory(): void

public static function extensionForMimeType(string $mimeType): ?string
```

Expected error statuses:

- `400` for malformed upload structures.
- `413` for files larger than 5 MB where detectable.
- `415` for unsupported image MIME types.
- `422` for a missing required photo or incomplete upload.

### 4. Integrate validation, upload storage, and insert cleanup

Complete `SubmissionsHandler::create()` by replacing placeholder insert values with:

1. Validate `$_POST` text fields.
2. Store the uploaded photo from `$_FILES`.
3. Insert a pending database row with the validated text values and stored photo filename.
4. If the database insert fails after moving the uploaded file, delete the uploaded file before returning or rethrowing an error.
5. Return the `201` success response.

Suggested helper method signature for cleanup:

```php
private static function deleteStoredPhotoIfPresent(?string $path): void
```

Do not log owner name, owner email, or raw submitted values.

## Manual verification

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

Negative cases to verify:

- Missing required text field returns `422`.
- Invalid email returns `422`.
- Missing photo returns `422` or `400`.
- Oversized photo returns `413` where detectable.
- Unsupported photo MIME returns `415`.
- `GET /data/submissions` returns `405`.
- Unknown route returns `404`.

## Security and privacy reminders

- Do not expose pending or rejected submissions from `GET /data/dogs`.
- Do not expose `owner_name`, `owner_email`, `photo_filename`, or internal filesystem paths.
- Do not add CORS for version 1; the frontend and API are same-origin behind Caddy.
- Treat uploaded files as untrusted even after MIME validation and moderator approval.
