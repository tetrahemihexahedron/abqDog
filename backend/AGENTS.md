# albuquerque.dog backend

## Project description

Small PHP 8 JSON API for albuquerque.dog. It runs as PHP-FPM behind Caddy, uses SQLite through PDO, and has no framework or Composer dependencies planned for the first version.

Initial routes:

- `GET /data/health` returns a simple health response.
- `GET /data/dogs` returns only approved dogs and must never expose private owner fields.
- `POST /data/submissions` accepts multipart dog submissions, validates fields and one uploaded photo, stores the photo, and inserts a pending row.

Use handwritten validation, prepared statements, environment-configured paths, JSON error responses with appropriate status codes, and defensive upload handling. Owner name and email are private moderation/contact data only.
