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

## Domain objects and services

Prefer small domain objects over passing large associative arrays between request parsing, upload handling, and database insertion. Keep constructors side-effect free: they may validate and normalize values, but they should not move files, create directories, write to the database, or emit responses.

Use these request/domain objects:

```php
final readonly class Request
```

`Request` is a tiny wrapper around PHP request globals so handlers and domain factories do not need to pass raw superglobals around.

Suggested constructor and factory signatures:

```php
/**
 * @param array<string, mixed> $post
 * @param array<string, mixed> $files
 */
public function __construct(
    public array $post,
    public array $files,
)

public static function fromGlobals(): self
```

```php
final readonly class DogPhoto
```

`DogPhoto` represents a stored server-side photo filename. Instantiate it from the generated filename and validate the filename in the constructor. It should not accept or preserve the client-provided filename.

Suggested method signatures:

```php
public function __construct(public string $filename)

public function path(): string
```

`path()` should derive the filesystem path from `Config::dogImageUploadDir()` and the validated filename.

```php
final readonly class DogSubmission
```

`DogSubmission` represents a valid public submission. Use a named factory to parse request data, trim and validate text fields, and combine them with an already-stored `DogPhoto`. The factory has no filesystem side effects; upload storage remains in `Uploads`.

Suggested constructor and factory signatures:

```php
private function __construct(
    public string $dogName,
    public string $description,
    public string $ownerName,
    public string $ownerEmail,
    public ?string $neighborhood,
    public DogPhoto $photo,
)

public static function fromRequest(Request $request, DogPhoto $photo): self
```

```php
final readonly class Dog
```

`Dog` is the object passed to the database insert helper. It represents a dog row ready for persistence. It is similar to `DogSubmission`, but adds `status`, `createdAt`, and `updatedAt`. Build it from a valid `DogSubmission`; `fromDogSubmission()` should assign the submission-specific status and timestamps internally using `Database::now()`.

Suggested constructor and factory signatures:

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

public static function fromDogSubmission(
    DogSubmission $submission
): self
```

For request validation failures that need field-addressable responses, use a small exception or result object rather than returning arrays from the domain objects.

Suggested exception signature:

```php
final class SubmissionValidationException extends InvalidArgumentException

/** @return array<string, string> */
public function fields(): array
```

Use `Uploads` as an application service for upload validation and storage. For upload operations that can fail without throwing, use a result object rather than a mixed array.

Suggested result class name:

```php
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

### 3. Done: added `Request`

Added `backend/src/Request.php` as a small `Request` class to wrap `$_POST` and `$_FILES` before adding the rest of the submission pipeline.

Suggested method signatures:

```php
/**
 * @param array<string, mixed> $post
 * @param array<string, mixed> $files
 */
public function __construct(
    public array $post,
    public array $files,
)

public static function fromGlobals(): self
```

### 4. Done: added `Dog` and the pending database insert

Added `backend/src/Dog.php` and completed the happy-path insert in `SubmissionsHandler::create()` using trusted placeholder values for fields that will later come from validation and upload storage. This makes it possible to verify database wiring before adding validation complexity.

Use `AbqDog\Database::connect()` and a prepared statement. Insert rows with the status supplied by `Dog`. For public submissions, `Dog::fromDogSubmission()` should assign `status` to `'pending'` and set `createdAt` and `updatedAt`.

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

Store `created_at` and `updated_at` as UTC ISO-8601 text values, matching the existing schema and public API format. Prefer this over changing to UNIX timestamp integers because the current strings are human-readable, lexicographically sortable in SQLite when consistently formatted, and can be returned directly by `GET /data/dogs`. Generate database timestamps through `Database::now()`, implemented with PHP's `DateTimeImmutable` and `DateTimeZone('UTC')`. The global PHP timezone is also set to UTC in `backend/php.ini` as a baseline, but persisted timestamps should still use the explicit database helper.

Implemented helper method signature:

```php
private static function insertDog(Dog $dog): int
```

`insertDog()` should return the inserted id if convenient, even if the API does not expose it yet. Keep it private on `SubmissionsHandler` for now, but write it so it can move to a repository later without changing the SQL behavior.

### 5. Handle database insertion errors and logging

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

### 6. Add request text validation

Add the text validation needed by `DogSubmission::fromRequest()` after the insert path and insertion-error handling are in place. The factory can be tested with a `Request` containing post data and a dummy valid `DogPhoto`, so these validation tests do not need real uploaded file fixtures.

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
public static function fromRequest(Request $request, DogPhoto $photo): self

public static function hasDisallowedControlCharacters(string $value): bool
```

If validation fails, throw `SubmissionValidationException` with field-addressable errors. The handler should catch it and return status `422` with the error shape described above.

### 7. Add upload validation and file storage

Add `backend/src/Uploads.php` after text validation is working. Keep upload validation and storage in this application service because it has filesystem side effects and the validation result determines the generated stored filename.

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
public static function storeDogPhoto(Request $request): PhotoUploadResult

public static function ensureUploadDirectory(): void

public static function extensionForMimeType(string $mimeType): ?string
```

Suggested result accessors or properties:

```php
public ?DogPhoto $photo
public ?string $error
public int $status
```

Expected error statuses:

- `400` for malformed upload structures.
- `413` for files larger than 5 MB where detectable.
- `415` for unsupported image MIME types.
- `422` for a missing required photo or incomplete upload.

### 8. Complete handler integration and cleanup

Complete `SubmissionsHandler::create()` by replacing placeholder insert values with:

1. Build a `Request` with `Request::fromGlobals()`.
2. Store the uploaded photo with `Uploads::storeDogPhoto($request)`, producing a `DogPhoto`.
3. Build a `DogSubmission` with `DogSubmission::fromRequest($request, $photo)`; if validation fails, delete the stored photo before returning validation errors.
4. Build a `Dog` with `Dog::fromDogSubmission($submission)`.
5. Insert the dog with `insertDog()`.
6. If the database insert fails after moving the uploaded file, delete the uploaded file before returning an error.
7. Return the `201` success response.

Suggested helper method signatures:

```php
private static function buildDog(DogSubmission $submission): Dog

private static function deleteStoredPhotoIfPresent(?DogPhoto $photo): void
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
