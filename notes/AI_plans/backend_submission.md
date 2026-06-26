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
final readonly class PhotoUpload
```

`PhotoUpload` represents a validated incoming dog photo upload that has not yet been moved to permanent storage. The constructor/factory may validate request upload metadata and detect MIME type, but it must not create directories, generate stored filenames, move files, or preserve user-provided filenames.

Suggested constructor and factory signatures:

```php
private const int MAX_BYTES = 5 * 1024 * 1024;

/** @var list<string> */
private const array ALLOWED_MIME_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp',
];

private function __construct(
    public string $temporaryPath,
    public int $size,
    public string $mimeType,
)

public static function fromRequest(
    Request $request,
    string $fieldName = 'photo',
): self
```

```php
final readonly class DogSubmission
```

`DogSubmission` represents a valid public submission before the uploaded photo has been stored. Use a named factory to parse request data, trim and validate text fields, and combine them with a validated incoming `PhotoUpload`. The factory has no filesystem side effects.

Suggested constructor and factory signatures:

```php
private function __construct(
    public string $dogName,
    public string $description,
    public string $ownerName,
    public string $ownerEmail,
    public ?string $neighborhood,
    public PhotoUpload $photo,
)

public static function fromRequest(Request $request): self
```

```php
final readonly class Dog
```

`Dog` is the object passed to the database insert helper. It represents a dog row ready for persistence. It is similar to `DogSubmission`, but adds the stored `DogPhoto`, `status`, `createdAt`, and `updatedAt`. Build it from a valid `DogSubmission` plus the stored `DogPhoto`; `fromSubmission()` should assign the submission-specific status and timestamps internally using `Database::now()`.

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

public static function fromSubmission(
    DogSubmission $submission,
    DogPhoto $photo,
): self
```

For request validation failures that need field-addressable responses, use a small exception rather than returning arrays from the domain objects. `DogSubmission::fromRequest()` should validate all text fields and create the `PhotoUpload`. It should catch `UploadException`, append the upload message to the field errors as `photo`, and throw one `SubmissionValidationException`. Store a response status on `SubmissionValidationException` so upload-specific statuses such as `413` and `415` can still be preserved while returning the same field-addressable error shape used for text validation.

Suggested exception signatures:

```php
final class SubmissionValidationException extends DomainException

public readonly int $status

/** @return array<string, string> */
public function fields(): array
```

```php
final class UploadException extends DomainException

public function __construct(
    string $message,
    public readonly int $status,
)
```

This simplified design intentionally does not add `Upload`, `StoredUpload`, or `UploadStorer` yet. `SubmissionsHandler` will own photo storage for now through a private helper that accepts a validated `PhotoUpload` and returns a stored `DogPhoto`.

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

Use `AbqDog\Database::connect()` and a prepared statement. Insert rows with the status supplied by `Dog`. For public submissions, `Dog::fromSubmission()` should assign `status` to `'pending'` and set `createdAt` and `updatedAt`.

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

Added `DogPhoto`, `DogSubmission`, and `SubmissionValidationException`. `DogSubmission::fromRequest()` currently performs text validation after the insert path and insertion-error handling are in place. In the refactor steps below, it will also create a validated `PhotoUpload`; text-only validation tests can still be written by separating text cases from upload cases or by providing a small valid upload fixture.

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
public static function fromRequest(Request $request): self

private static function hasBadCharacters(string $value): bool
```

Keep low-level text helpers private on `DogSubmission` for now; extracting a separate text-validation service would be premature unless the rules need to be reused elsewhere. In validation `switch (true)` blocks, check blank values first and bad/control characters before length or format checks so hidden unsupported characters are reported clearly. If validation fails, throw `SubmissionValidationException` with field-addressable errors and a status, defaulting to `422`. The handler should catch it and return `$exception->status` with the error shape described above.

### 7. Refactor upload validation into `PhotoUpload`

Refactor the current upload direction to a smaller first-version design. There should be no separate `Upload`, `StoredUpload`, or `UploadStorer` class for now. `PhotoUpload` validates the incoming upload, and `SubmissionsHandler` stores it.

#### 7.1. Done: keep `UploadException`

`UploadException` remains useful as an API-safe exception for invalid incoming uploads.

Suggested signature:

```php
final class UploadException extends DomainException
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
3. `DogSubmission::fromRequest()` should catch this exception and convert it into a `SubmissionValidationException` field error for `photo`, preserving `$exception->status` on the submission exception.
4. Do not log validation exceptions; they represent client-correctable input. Only log unexpected storage/database/server failures, without private fields or client filenames.

Expected validation error statuses and messages:

- `400` for malformed upload structures, for example `"That photo upload was malformed. Please try choosing the file again."`
- `413` for files larger than 5 MB where detectable, for example `"That photo is too big. Please choose an image under 5 MB."`
- `415` for unsupported image MIME types, for example `"That photo type is not supported. Please use a JPG, PNG, or WebP image."`
- `422` for a missing required photo, empty photo, or incomplete upload, for example `"Please add a photo of your dog."` or `"The photo upload did not finish. Please try again."`

#### 7.2. Done: replace base `Upload` with standalone `PhotoUpload`

Delete or stop using the base `Upload` class. Move the validation behavior currently in `Upload` into `PhotoUpload`, combined with the photo-specific MIME rules.

`PhotoUpload` rules:

1. Require exactly one uploaded file under the `photo` field by default.
2. Reject missing, malformed, empty, partial, or multiple uploads.
3. Reject files larger than 5 MB.
4. Keep only server-relevant data such as the temporary filename, detected size, and detected MIME type. Do not preserve user-provided filenames.
5. Validate MIME type with PHP's `finfo` object API, not the client filename or `$_FILES['photo']['type']`.
6. Accept only:
   - `image/jpeg`
   - `image/png`
   - `image/webp`
7. On validation failure, throw `UploadException`; on success, return a valid `PhotoUpload` object.
8. Do not generate stored filenames, create directories, move files, or expose stored filenames.

Suggested signature:

```php
final readonly class PhotoUpload
{
    private const int MAX_BYTES = 5 * 1024 * 1024;

    /** @var list<string> */
    private const array ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    private function __construct(
        public string $temporaryPath,
        public int $size,
        public string $mimeType,
    )

    public static function fromRequest(
        Request $request,
        string $fieldName = 'photo',
    ): self
}
```

Validation exception details:

- Treat `UPLOAD_ERR_NO_FILE` as `422`.
- Treat `UPLOAD_ERR_INI_SIZE` and `UPLOAD_ERR_FORM_SIZE` as `413`.
- Treat `UPLOAD_ERR_PARTIAL` as `422`.
- Treat unknown upload error codes, array-valued fields, missing `tmp_name`/`size`/`error`, non-string `tmp_name`, non-int-like `size`, non-int-like `error`, or failed MIME detection as `400`.
- Treat unsupported detected MIME types as `415`.

#### 7.3. Done: refactored `DogSubmission`

Change `DogSubmission::fromRequest()` so the handler can create the submission in one call:

```php
public static function fromRequest(Request $request): self
```

`DogSubmission::fromRequest()` should:

1. Validate and normalize text fields as it does now.
2. Build a validated `PhotoUpload` from the same request.
3. Catch `UploadException` from `PhotoUpload::fromRequest()` and add `$fields['photo'] = $exception->getMessage()`.
4. Throw one `SubmissionValidationException` if any text or photo validation fails.
5. Set the `SubmissionValidationException` status to the upload exception status when the upload failed; otherwise use `422`. If both text fields and upload fail, using the upload status is acceptable so oversized/unsupported uploads can still return `413`/`415`.
6. Return a `DogSubmission` containing validated text fields and a validated, unstored `PhotoUpload`.

Suggested constructor shape:

```php
private function __construct(
    public string $dogName,
    public string $description,
    public string $ownerName,
    public string $ownerEmail,
    public ?string $neighborhood,
    public PhotoUpload $photo,
)
```

#### 7.4. Done: kept `DogPhoto` as the stored-photo value object

`DogPhoto` continues to represent the generated server-side filename after the photo has been moved. It should not accept or preserve the client-provided filename.

Suggested signature:

```php
public function __construct(public string $filename)

public function path(): string
```

#### 7.5. Done: refactored `Dog`

Change the factory name and inputs so it clearly accepts the validated submission plus the stored photo:

```php
public static function fromSubmission(
    DogSubmission $submission,
    DogPhoto $photo,
): self
```

`fromSubmission()` should copy public/private fields from `DogSubmission`, assign the supplied stored `DogPhoto`, set `status` to `'pending'`, and set `createdAt` and `updatedAt` through `Database::now()`.

### 8. Add handler-owned photo storage

For now, keep photo storage on `SubmissionsHandler` instead of adding an `UploadStorer` service. This is simpler, but it is a conscious tradeoff: the handler will mix HTTP orchestration with filesystem side effects. If upload storage gains reuse, variants, or more tests, extract it to a service later.

Add helper methods to `SubmissionsHandler`:

```php
private static function storePhoto(PhotoUpload $photo): DogPhoto

private static function extensionForMimeType(string $mimeType): string

private static function ensureUploadDirectory(): void

private static function deleteStoredPhotoIfPresent(?DogPhoto $photo): void
```

Storage rules:

1. Keep the MIME-to-extension map on `SubmissionsHandler` for now:
   - `image/jpeg` -> `.jpg`
   - `image/png` -> `.png`
   - `image/webp` -> `.webp`
2. Generate the stored filename server-side, for example `bin2hex(random_bytes(16)) . $extension`.
3. Ensure `Config::dogImageUploadDir()` exists and is writable; create it if needed.
4. Store with `move_uploaded_file($photo->temporaryPath, $destination)` only.
5. Return a `DogPhoto` built from the generated filename.
6. Do not preserve user-provided filenames.
7. On storage failure, log a generic server-side message and return a `500` response using the shared error shape.

### 9. Complete handler integration and cleanup

Refactor `SubmissionsHandler::create()` to use this shape:

```php
public static function create(): Response
{
    $request = Request::fromGlobals();

    try {
        $submission = DogSubmission::fromRequest($request);
    } catch (SubmissionValidationException $exception) {
        return Http::jsonResponse([
            'error' => $exception->getMessage(),
            'fields' => $exception->fields(),
        ], $exception->status);
    }

    $dogPhoto = null;

    try {
        $dogPhoto = self::storePhoto($submission->photo);
        $dog = Dog::fromSubmission($submission, $dogPhoto);
        self::insertDog($dog);
    } catch (Throwable $exception) {
        self::deleteStoredPhotoIfPresent($dogPhoto);
        Logger::error('Could not save submission.', $exception);
        return Http::jsonResponse(['error' => 'Could not save submission.'], 500);
    }

    return Http::jsonResponse([
        'ok' => true,
        'message' => 'Submission received.',
    ], 201);
}
```

Implementation cleanup steps:

1. Delete or stop using `backend/src/Upload.php`.
2. Add `backend/src/PhotoUpload.php` with the combined upload validation rules.
3. Update `backend/src/DogSubmission.php` so `fromRequest()` accepts only `Request` and stores a `PhotoUpload`.
4. Update `backend/src/Dog.php` from `fromDogSubmission()` to `fromSubmission(DogSubmission $submission, DogPhoto $photo)`.
5. Update `backend/src/Handlers/SubmissionsHandler.php` to validate, store the photo, insert the dog, clean up stored photos on later failures, and return the final `201` response.
6. Keep `insertDog(Dog $dog): int` private on `SubmissionsHandler` for now.

Do not log owner name, owner email, descriptions, raw submitted values, uploaded client filenames, temporary paths, or full request payloads.

### Design tradeoffs to revisit later

- Keeping `storePhoto()` on `SubmissionsHandler` is intentionally simple, but it mixes request orchestration with filesystem storage. If upload storage needs reuse, direct unit testing, alternate directories, or background processing, extract it to an `UploadStorer`/`PhotoStorage` service.
- `DogSubmission::fromRequest()` will validate both text fields and upload metadata. That gives the handler the desired simple shape, but it means a submission domain factory depends on request-upload details. If this grows, introduce a small request parser or application service.
- Converting `UploadException` into `SubmissionValidationException` gives the frontend one field-error shape, including `fields.photo`. The slightly odd part is that a response with a `fields` object may sometimes use status `413` or `415` instead of `422`; this is intentional so oversized and unsupported uploads are still represented accurately.

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
