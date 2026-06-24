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

`DogSubmission` represents a valid public submission. Use a named factory to parse request data, trim and validate text fields, and combine them with an already-stored `DogPhoto`. The factory has no filesystem side effects; upload storage remains in `UploadStorer`.

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
    public DogPhoto $photo,
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
final class SubmissionValidationException extends DomainException

/** @return array<string, string> */
public function fields(): array
```

Model incoming uploads separately from stored files. Use a base `Upload` domain object for validation that applies to any uploaded file, a `PhotoUpload` subclass for image-specific rules, a `StoredUpload` value object for the generated server-side filename/path after storage, and an `UploadStorer` application service for filesystem side effects. `PhotoUpload` and `UploadStorer` should not know about dogs; convert a `StoredUpload` to `DogPhoto` at the submission boundary.

Upload factories should either throw or return a valid object; do not use a result object for upload validation. Throw an `UploadValidationException` containing an API-safe message and HTTP status for validation errors.

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

### 5. Done: handled database insertion errors and added logging

Added `backend/src/Logger.php` as a small wrapper around `error_log()`. `SubmissionsHandler::create()` now catches insertion failures, logs a generic server-side message with exception details, and returns a submission-specific `500` response before validation and upload complexity are added. `public/index.php` also logs unexpected uncaught API exceptions.

Implemented behavior:

- Catch insertion exceptions at the handler boundary where a submission response can still be chosen.
- Log a generic server-side message and exception details through `Logger::error()`.
- Do not log owner name, owner email, descriptions, raw submitted fields, uploaded filenames from the client, or full request payloads.
- Return a JSON `500` response using the shared error shape:

```json
{
  "error": "Could not save submission."
}
```

No submission-specific logging helper is needed for now; call `Logger::error()` directly at the single insertion-failure catch site.

### 6. Done: added request text validation

Added `DogPhoto`, `DogSubmission`, and `SubmissionValidationException`. `DogSubmission::fromRequest()` performs text validation after the insert path and insertion-error handling are in place. The factory can be tested with a `Request` containing post data and a dummy valid `DogPhoto`, so these validation tests do not need real uploaded file fixtures.

Rules:

- Trim all string fields.
- `dog_name`: required, 1-80 characters.
- `description`: required, 10-500 characters.
- `owner_name`: required, 1-120 characters.
- `owner_email`: required, valid email, max 254 characters.
- `neighborhood`: optional, max 120 characters; store `NULL` if blank.
- Reject control characters except normal whitespace.

Implemented method signatures:

```php
public static function fromRequest(Request $request, DogPhoto $photo): self

private static function hasBadCharacters(string $value): bool
```

Keep low-level text helpers private on `DogSubmission` for now; extracting a separate text-validation service would be premature unless the rules need to be reused elsewhere. In validation `switch (true)` blocks, check blank values first and bad/control characters before length or format checks so hidden unsupported characters are reported clearly. If validation fails, throw `SubmissionValidationException` with field-addressable errors. The handler should catch it and return status `422` with the error shape described above.

### 7. Add upload validation and file storage

Add upload handling after text validation is working. Keep domain validation separate from filesystem storage:

- `backend/src/Upload.php`: base upload domain object for validation required by any uploaded file.
- `backend/src/UploadValidationException.php`: API-safe validation exception for invalid incoming uploads.
- `backend/src/PhotoUpload.php`: image-specific upload object that extends `Upload` and validates accepted image MIME types.
- `backend/src/StoredUpload.php`: value object for a stored server-side upload filename and directory.
- `backend/src/UploadStorer.php`: application service that stores a validated `Upload` on the filesystem and returns a `StoredUpload`.

Constructors may validate and normalize upload metadata, but they must remain side-effect free. `Upload` should describe the incoming temporary upload and should not track the final stored filename. The stored filename is only known after the storage side effect succeeds, so keep it in a separate `StoredUpload` value object. Directory creation, filename generation, extension selection, and `move_uploaded_file()` belong in `UploadStorer`.

#### 7.1. Done: add `UploadValidationException`

Upload validation errors should be represented by `UploadValidationException`, not by `null`, partial objects, mixed arrays, or result objects. Upload factories should either throw or return a valid object.

Suggested signature:

```php
final class UploadValidationException extends DomainException
{
    public function __construct(
        string $message,
        public readonly int $status,
    )
}
```

Rules:

1. The exception message must be safe to return to the API client.
2. Do not include client filenames, temporary paths, raw upload arrays, owner fields, or submitted text values.
3. The handler should catch this exception and return the shared error shape with `$exception->status`.
4. Do not log validation exceptions; they represent client-correctable input. Only log unexpected storage/database/server failures, without private fields or client filenames.

Example handler shape:

```php
try {
    $upload = PhotoUpload::fromRequest($request);
} catch (UploadValidationException $exception) {
    return Response::json(['error' => $exception->getMessage()], $exception->status);
}
```

Expected validation error statuses and messages:

- `400` for malformed upload structures, for example `"That photo upload was malformed. Please try choosing the file again."`
- `413` for files larger than 5 MB where detectable, for example `"That photo is too big. Please choose an image under 5 MB."`
- `415` for unsupported image MIME types, for example `"That photo type is not supported. Please use a JPG, PNG, or WebP image."`
- `422` for a missing required photo or incomplete upload, for example `"Please add a photo of your dog."` or `"The photo upload did not finish. Please try again."`

#### 7.2. Done: add base `Upload`

Base `Upload` rules:

1. Require exactly one uploaded file under the requested field name.
2. Reject missing, malformed, empty, partial, or multiple uploads.
3. Reject files larger than the supplied maximum size, 5 MB for dog photos.
4. Keep only server-relevant data such as the field name, temporary filename, detected size, detected MIME type, and allowed MIME types. Do not preserve user-provided filenames.
5. Validate MIME type with `finfo_file`, not the client filename or `$_FILES['photo']['type']`.
6. Support an allowed MIME type list, for example `['image/jpeg']`. An empty list means the base class applies no MIME restriction beyond detecting the MIME type successfully. Subclasses such as `PhotoUpload` provide restrictions by passing a non-empty list.
7. Do not put file-extension behavior on `Upload`; the base class validates the incoming upload but does not decide how stored filenames are formed.
8. On validation failure, throw `UploadValidationException`; on success, return a valid `Upload` object.

Suggested signature:

```php
abstract readonly class Upload
{
    /** @param list<string> $allowedMimeTypes */
    protected function __construct(
        public string $fieldName,
        public string $temporaryPath,
        public int $size,
        public string $mimeType,
        public array $allowedMimeTypes = [],
    )

    /** @param list<string> $allowedMimeTypes */
    protected static function fromRequest(
        Request $request,
        string $fieldName,
        int $maxBytes,
        array $allowedMimeTypes = [],
    ): static
}
```

Validation exception details:

- Treat `UPLOAD_ERR_NO_FILE` as `422`.
- Treat `UPLOAD_ERR_INI_SIZE` and `UPLOAD_ERR_FORM_SIZE` as `413`.
- Treat `UPLOAD_ERR_PARTIAL` as `422`.
- Treat unknown upload error codes, array-valued fields, missing `tmp_name`/`size`/`error`, non-string `tmp_name`, non-int-like `size`, non-int-like `error`, or failed MIME detection as `400`.
- Treat unsupported detected MIME types as `415` when the upload subclass provides a non-empty allowed MIME type list.

#### 7.3. Add `PhotoUpload`

`PhotoUpload` rules:

1. Accept the field name and maximum size from its factory, rather than hard-coding dog-submission concepts in the base `Upload` class.
2. For dog submissions, call the factory with the `photo` field and a 5 MB maximum size.
3. Accept only these MIME types:
   - `image/jpeg`
   - `image/png`
   - `image/webp`
4. Provide its allowed MIME type list to the base `Upload` factory.
5. Do not know or expose file extensions. `PhotoUpload` validates that the detected MIME type is an acceptable photo MIME type; `UploadStorer` is responsible for mapping accepted MIME types to stored filename extensions.

Suggested signature:

```php
final readonly class PhotoUpload extends Upload
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    /** @var list<string> */
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public static function fromRequest(
        Request $request,
        string $fieldName = 'photo',
        int $maxBytes = self::MAX_BYTES,
    ): self

    /** @return list<string> */
    public static function allowedMimeTypes(): array
}
```

#### 7.4. Add `StoredUpload`

`StoredUpload` rules:

1. Represent the generated stored filename and storage directory after a successful move.
2. Validate the generated filename defensively.
3. Provide a `path()` helper that joins the directory and filename.
4. Do not include or preserve user-provided filenames.

Suggested signature:

```php
final readonly class StoredUpload
{
    public function __construct(
        public string $directory,
        public string $filename,
    )

    public function path(): string
}
```

#### 7.5. Add `UploadStorer`

`UploadStorer` rules:

1. Accept the target upload directory as configuration through the constructor, so the service is not dog-specific.
2. Keep a class-level MIME-to-extension map. It does not need to be configured per use right now. It is okay if the initial map only contains image types:
   - `image/jpeg` -> `.jpg`
   - `image/png` -> `.png`
   - `image/webp` -> `.webp`
3. Look up the detected MIME type from the validated `Upload` in that class-level map. If there is no extension for the upload's MIME type, store without an extension rather than treating it as a client validation error.
4. Generate the stored filename server-side, for example `bin2hex(random_bytes(16)) . $extension`.
5. Ensure the configured upload directory exists and is writable; create it if needed.
6. Store with `move_uploaded_file` only.
7. Return a `StoredUpload` built from the generated filename and configured directory.

Suggested signature:

```php
final readonly class UploadStorer
{
    /** @var array<string, string> */
    private const EXTENSIONS_BY_MIME_TYPE = [
        'image/jpeg' => '.jpg',
        'image/png' => '.png',
        'image/webp' => '.webp',
    ];

    public function __construct(public string $directory)

    public function store(Upload $upload): StoredUpload

    private function extensionFor(Upload $upload): string

    private function ensureUploadDirectory(): void
}
```

Successful storage returns `StoredUpload` from `UploadStorer`. `UploadStorer` owns the stored filename extension decision; `Upload` and `PhotoUpload` only validate the incoming upload. The submission handler can then build `DogPhoto` from `$storedUpload->filename` because dog rows currently persist only the generated filename.

### 8. Complete handler integration and cleanup

Complete `SubmissionsHandler::create()` by replacing placeholder insert values with:

1. Build a `Request` with `Request::fromGlobals()`.
2. Build a `PhotoUpload` from the request. If `PhotoUpload::fromRequest()` throws `UploadValidationException`, return its API-safe message and status without attempting storage or logging the validation failure. Otherwise, store it with `new UploadStorer(Config::dogImageUploadDir())`, producing a `StoredUpload`.
3. Build a `DogPhoto` from `$storedUpload->filename`.
4. Build a `DogSubmission` with `DogSubmission::fromRequest($request, $photo)`; if validation fails, delete the stored upload before returning validation errors.
5. Build a `Dog` with `Dog::fromDogSubmission($submission)`.
6. Insert the dog with `insertDog()`.
7. If the database insert fails after moving the uploaded file, delete the uploaded file before returning an error.
8. Return the `201` success response.

Suggested helper method signatures:

```php
private static function buildDog(DogSubmission $submission): Dog

private static function deleteStoredUploadIfPresent(?StoredUpload $upload): void
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
