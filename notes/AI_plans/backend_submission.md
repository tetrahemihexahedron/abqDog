# Backend submission endpoint plan

This document expands step 7 of `backend_api.md`: implement `POST /data/submissions`.

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

## Response shapes

Successful response, status `201`:

```json
{
  "ok": true,
  "message": "Submission received."
}
```

Validation error response, status `422`:

```json
{
  "error": "Validation failed.",
  "fields": {
    "dog_name": "Dog name is required."
  }
}
```

Other error responses should use the shared error shape:

```json
{
  "error": "Unsupported image type."
}
```

Only success responses include `ok`. Error responses should not include `ok`.

Do not return owner name, owner email, uploaded filesystem paths, or moderation status. Returning the new database id is optional; if returned, treat it as an internal reference rather than a public dog page id.

## Data objects

Prefer small data objects over passing large associative arrays between validation, upload handling, and database insertion.

Suggested classes:

```php
final readonly class ValidatedSubmissionText
```

Suggested constructor shape:

```php
public function __construct(
    public string $dogName,
    public string $description,
    public string $ownerName,
    public string $ownerEmail,
    public ?string $neighborhood,
)
```

```php
final readonly class StoredDogPhoto
```

Suggested constructor shape:

```php
public function __construct(
    public string $filename,
    public string $path,
)
```

```php
final readonly class DogInsertData
```

Suggested constructor shape:

```php
public function __construct(
    public string $dogName,
    public string $description,
    public string $photoFilename,
    public string $ownerName,
    public string $ownerEmail,
    public ?string $neighborhood,
    public string $status,
    public string $createdAt,
    public string $updatedAt,
)
```

`DogInsertData` is the object passed to the database insert helper. The handler should set `status` to `'pending'` before calling the helper so the insert helper can remain a more general `insertDog()` operation and later move easily to a `Dog` model or repository.

For operations that can fail without throwing, use result objects rather than mixed arrays.

Suggested result class names:

```php
final readonly class SubmissionTextValidationResult
final readonly class PhotoUploadResult
```

## Implementation steps

### 1. Done: add initial handler shape

`backend/src/Handlers/SubmissionsHandler.php` exists with this handler method:

```php
public static function create(): Response
```

The method currently exists as a placeholder until the remaining steps are implemented.

### 2. Done: register the route

`backend/public/index.php` contains:

```php
'/data/submissions' => [
    'POST' => [SubmissionsHandler::class, 'create'],
],
```

### 3. Add pending database insert

First complete the happy-path insert in `SubmissionsHandler::create()` using trusted placeholder values for fields that will later come from validation and upload storage. This makes it possible to verify database wiring before adding validation complexity.

Use `AbqDog\Database::connect()` and a prepared statement. Insert rows with the status supplied by `DogInsertData`; the handler should set that status to `'pending'` for public submissions.

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
  :status,
  :created_at,
  :updated_at
);
```

Use one UTC ISO-8601 timestamp value for both `created_at` and `updated_at`.

Suggested helper method signatures:

```php
private static function insertDog(DogInsertData $dog): int

private static function utcTimestamp(): string
```

`insertDog()` should return the inserted id if convenient, even if the API does not expose it yet. Keep it private on `SubmissionsHandler` for now, but write it so it can move to a `Dog` model or repository later without changing the SQL behavior.

### 4. Handle database insertion errors and logging

After the insert works on the happy path, add explicit error handling around database insertion before adding validation and upload complexity.

Recommended behavior:

- Catch database exceptions at the handler boundary where a submission response can still be chosen.
- Log a generic server-side message and exception details with `error_log()` or the project’s chosen logger.
- Do not log owner name, owner email, descriptions, raw submitted fields, uploaded filenames from the client, or full request payloads.
- Return a JSON `500` response using the shared error shape:

```json
{
  "error": "Could not save submission."
}
```

If `public/index.php` is the only current unexpected-exception logger and it does not log details, add appropriate logging there or in this handler step so insertion failures are diagnosable without exposing private data to clients.

Suggested helper method signature:

```php
private static function logSubmissionInsertFailure(Throwable $exception): void
```

### 5. Add text-field validation

Add `backend/src/Validation.php` after the insert path and insertion-error handling are in place. Keep text validation independent from uploads so it can be tested without file fixtures.

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
/** @param array<string, mixed> $input */
public static function validateSubmissionText(array $input): SubmissionTextValidationResult

public static function hasDisallowedControlCharacters(string $value): bool
```

Suggested result accessors or properties:

```php
public ?ValidatedSubmissionText $value

/** @var array<string, string> */
public array $errors
```

Use status `422` for text validation failures and the error shape described above.

### 6. Add upload validation and file storage

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
/** @param array<string, mixed> $files */
public static function storeDogPhoto(array $files): PhotoUploadResult

public static function ensureUploadDirectory(): void

public static function extensionForMimeType(string $mimeType): ?string
```

Suggested result accessors or properties:

```php
public ?StoredDogPhoto $photo
public ?string $error
public int $status
```

Expected error statuses:

- `400` for malformed upload structures.
- `413` for files larger than 5 MB where detectable.
- `415` for unsupported image MIME types.
- `422` for a missing required photo or incomplete upload.

### 7. Complete handler integration and cleanup

Complete `SubmissionsHandler::create()` by replacing placeholder insert values with:

1. Validate `$_POST` text fields.
2. Store the uploaded photo from `$_FILES`.
3. Build a `DogInsertData` instance from `ValidatedSubmissionText`, `StoredDogPhoto`, status `'pending'`, and timestamps.
4. Insert the dog with `insertDog()`.
5. If the database insert fails after moving the uploaded file, delete the uploaded file before returning an error.
6. Return the `201` success response.

Suggested helper method signatures:

```php
private static function buildPendingDogInsertData(
    ValidatedSubmissionText $text,
    StoredDogPhoto $photo,
): DogInsertData

private static function deleteStoredPhotoIfPresent(?StoredDogPhoto $photo): void
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
- Database insertion failure is logged server-side and returns `500` without private data.
- `GET /data/submissions` returns `405`.
- Unknown route returns `404`.

## Security and privacy reminders

- Do not expose pending or rejected submissions from `GET /data/dogs`.
- Do not expose `owner_name`, `owner_email`, `photo_filename`, or internal filesystem paths.
- Do not log owner name, owner email, descriptions, raw submitted values, or full request payloads.
- Do not add CORS for version 1; the frontend and API are same-origin behind Caddy.
- Treat uploaded files as untrusted even after MIME validation and moderator approval.
