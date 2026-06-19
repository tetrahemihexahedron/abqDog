# albuquerque.dog backend

## Project description

Small PHP 8 JSON API for albuquerque.dog. It runs as PHP-FPM behind Caddy, uses SQLite through PDO, and has no framework or Composer dependencies planned for the first version.

Initial routes:

- `GET /data/health` returns a simple health response.
- `GET /data/dogs` returns only approved dogs and must never expose private owner fields.
- `POST /data/submissions` accepts multipart dog submissions, validates fields and one uploaded photo, stores the photo, and inserts a pending row.

Use handwritten validation, prepared statements, environment-configured paths, JSON error responses with appropriate status codes, and defensive upload handling. Owner name and email are private moderation/contact data only.

## Backend design decisions

- Handler method names should describe endpoint intent where possible.
- Keep route definitions centralized in `backend/public/index.php`; handlers should return `Response` objects and should not emit headers/body directly.
- Use a small `Request` wrapper instead of passing superglobals throughout submission code.
- Prefer small domain/value objects over large associative arrays for submission data. Current direction: `DogPhoto` for generated stored filenames, `DogSubmission` for validated public submission data, and `Dog` for a dog row ready for persistence.
- Keep domain constructors side-effect free. File upload validation/storage belongs in an application service such as `Uploads`, not in `DogSubmission` or `DogPhoto` constructors.
- Keep insert/select SQL in the handlers for now and move it later to a repository/service if needed.
- Store database timestamps as UTC ISO-8601 text strings matching the existing SQLite schema and public API. Use `Database::now()` for persisted timestamps. `backend/php.ini` should set `date.timezone = UTC` as a baseline.
- Use a tiny `Logger` wrapper around `error_log()` rather than adding a logging dependency for now. Do not log owner name, owner email, raw submitted fields, uploaded client filenames, or full request payloads.
- Success responses may include `ok: true`; error responses use the shared `{ "error": "..." }` shape and should not include `ok`.
