# Development Plan for albuquerque.dog

## Goals

Build a small, maintainable website for showcasing dogs that live in Albuquerque, New Mexico. The first version will have:

- a landing page showing approved dogs;
- a dog submission page;
- a small SQLite database for submitted dog information;
- Docker-based deployment behind Caddy.

## Technology choices

### Frontend

- **Language:** TypeScript
- **UI library:** React
- **Build tool:** Vite
- **Styling:** plain CSS files using CSS custom properties
- **Routing:** minimal custom route switch based on `window.location.pathname`
  - Initial routes are simple enough that React Router is not necessary.
  - React Router can be added later if navigation becomes more complex.
- **Forms:** plain React controlled form components
- **Validation:** small handwritten TypeScript validation functions
- **Testing, initial phase:** manual testing plus TypeScript checks
- **Testing, later optional:** Vitest if automated frontend tests become useful

### Backend

- **Language:** PHP 8.3+
- **Server interface:** PHP-FPM behind Caddy
- **Database:** SQLite
- **Database access:** PHP PDO with the built-in SQLite driver
- **Validation:** handwritten PHP validation functions
- **File uploads:** native PHP multipart upload handling
- **API format:** JSON over HTTP
- **CORS:** no cross-origin support for version 1; the frontend and API are served from the same origin by Caddy

PHP is a good fit here because it keeps the backend small. SQLite support is available through PDO, file uploads are built in, and no backend package manager or framework is required for the first version.

### Deployment

- **Containerization:** Docker and Docker Compose
- **Reverse proxy / web server:** Caddy
- **Runtime containers:**
  - Caddy container serving the built React app and uploaded dog images
  - PHP-FPM container running the backend API
- **Persistent storage:** Docker volumes or bind mounts for:
  - SQLite database file
  - uploaded dog photos
- **Configurable paths:** backend runtime paths should come from environment variables where practical:
  - `DATABASE_PATH=/data/albuquerque-dog.sqlite`
  - `UPLOAD_DIR=/uploads/dogs`
  - `PUBLIC_UPLOAD_BASE=/uploads/dogs`

## Proposed project structure

```text
.
├── frontend/
│   ├── Dockerfile
│   ├── src/
│   │   ├── components/
│   │   ├── pages/
│   │   ├── api.ts
│   │   ├── validation.ts
│   │   ├── types.ts
│   │   ├── main.tsx
│   │   └── styles.css
│   ├── index.html
│   ├── package.json
│   ├── tsconfig.json
│   └── vite.config.ts
├── backend/
│   ├── Dockerfile
│   ├── public/
│   │   └── index.php
│   ├── src/
│   │   ├── db.php
│   │   ├── dogs.php
│   │   ├── submissions.php
│   │   ├── validation.php
│   │   └── response.php
│   ├── migrations/
│   │   └── 001_initial_schema.sql
│   ├── php.ini
│   └── seed.sql
├── web/
│   └── Caddyfile
├── compose.yml
└── README.md
```

## Phase 1: Basic project setup

1. Create `frontend/` as a Vite React TypeScript app.
2. Create `backend/` as a small PHP application with no Composer dependencies.
3. Add Dockerfiles for backend and frontend images and a root-level compose file, with a Caddy service.
4. Create an override compose file and images appropriate for local development.
5. Add local development instructions to the root README.

## Phase 2: Database design

Use a single SQLite database file, stored by default at:

```text
/data/albuquerque-dog.sqlite
```

The path should be configurable with `DATABASE_PATH` so development, test, and production environments can use different files.

Create the initial table with plain SQL migrations. Use a simple numbered migration convention:

```text
backend/migrations/001_initial_schema.sql
backend/migrations/002_add_example.sql
```

The first version can apply migrations manually with `sqlite3`. Automated migration application can be added later if needed.

### `dogs` table

Fields:

- `id` integer primary key
- `dog_name` text, required, `NOT NULL`
- `short_description` text, required, `NOT NULL`
- `photo_filename` text, required, `NOT NULL`
- `owner_name` text, required, private, `NOT NULL`
- `owner_email` text, required, private, `NOT NULL`
- `neighborhood` text, optional
- `status` text, required, `NOT NULL`, one of `pending`, `approved`, `rejected`
- `created_at` text, required, `NOT NULL`, ISO-8601 UTC timestamp
- `updated_at` text, required, `NOT NULL`, ISO-8601 UTC timestamp

Recommended constraints:

- `CHECK(status IN ('pending', 'approved', 'rejected'))`
- default `status` of `pending` if it simplifies insert logic
- timestamps stored with a consistent UTC format, for example `strftime('%Y-%m-%dT%H:%M:%SZ', 'now')`

Indexes:

- index on `status`
- index on `created_at`

The public API must never return private owner fields. If a photo is exposed through the API, return a public URL such as `/uploads/dogs/example.webp`, not an internal filesystem path.

## Phase 3: Backend API

Build a small PHP API with explicit route handling in `backend/public/index.php`.

Initial routes:

- `GET /data/health`
  - returns `{ "ok": true }`
- `GET /data/dogs`
  - returns approved dogs only
  - excludes private owner fields
  - returns a public `photo_url`, derived from `PUBLIC_UPLOAD_BASE` and `photo_filename`
- `POST /data/submissions`
  - accepts multipart form data
  - validates text fields
  - validates and stores one uploaded dog photo
  - inserts the submission with `status = 'pending'`

Implementation details:

1. Use `$_SERVER['REQUEST_METHOD']` and `parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)` for routing.
2. Use PDO prepared statements for all database access.
3. Use handwritten validation functions in `backend/src/validation.php`.
4. Store uploaded photos in `UPLOAD_DIR`, defaulting to:

   ```text
   /uploads/dogs
   ```

5. Generate safe photo filenames server-side using random bytes.
6. Validate uploads defensively:
   - require exactly one non-empty uploaded file
   - use `finfo_file` to validate MIME type, not just the client-provided extension
   - accept only JPEG, PNG, and WebP
   - choose the stored extension from the validated MIME type
   - move files only with `move_uploaded_file`
7. Limit uploads to 5 MB.
8. Set PHP upload limits in `deploy/php.ini`, including `upload_max_filesize` and `post_max_size`.
9. Return JSON error responses with appropriate HTTP status codes.
10. Assume same-origin requests in version 1; do not add CORS headers unless a future deployment needs them.

## Phase 4: Frontend application shell

Build the React app without a routing dependency.

1. Create a tiny route switch:

   ```ts
   const path = window.location.pathname;
   ```

2. Render:
   - `/` as the landing page
   - `/submit` as the submission page
   - anything else as a simple not-found page
3. Use normal anchor tags for navigation.
4. Create shared layout components:
   - `Header`
   - `Footer`
   - `PageLayout`
5. Use plain CSS for layout, colors, spacing, responsive behavior, and typography.

## Phase 5: Landing page

1. Fetch approved dogs from `GET /data/dogs`.
2. Display dog cards in a responsive grid.
3. Each card should show:
   - photo
   - dog name
   - short description
   - neighborhood, if provided
4. Add simple loading, error, and empty states.
5. Include a clear link to `/submit`.

## Phase 6: Dog submission form

Build the form with plain React state and browser APIs.

Fields:

- dog name
- short description
- dog photo
- owner name
- owner email
- neighborhood

Behavior:

1. Validate fields in TypeScript before submission.
2. Use `FormData` to submit multipart data to `POST /data/submissions`.
3. Show field-level validation messages where useful.
4. Explain that owner name and email are private and only used for moderation/contact.
5. Show a success message after submission.
6. Clear the form after a successful submission.

## Phase 7: Caddy and Docker setup

Use Caddy as the public entrypoint.

Caddy responsibilities:

1. Serve the built React app from `/srv/www`.
2. Route `/data/*` requests to PHP-FPM.
3. Serve uploaded dog images from `/uploads/dogs` under a public URL such as `/uploads/dogs/...`.
4. For frontend routes, fall back to `index.html`.
5. In production, handle HTTPS automatically for `albuquerque.dog`.
6. Add basic security headers where practical:
   - `X-Content-Type-Options: nosniff`
   - `Referrer-Policy`
   - a conservative `Content-Security-Policy` later if it does not interfere with Vite-built assets

Docker Compose services:

- `caddy`
- `backend` running PHP-FPM

Persistent volumes:

- `sqlite-data:/data`
- `uploaded-images:/uploads`

Operational note: back up both the SQLite database and uploaded images, especially before deployments or schema changes:

- `/data/albuquerque-dog.sqlite`
- `/uploads/dogs`

## Phase 8: Privacy and moderation workflow for version 1

Owner name and email are collected only for moderation/contact. They are private fields and must not be exposed by public API responses, frontend state intended for display, logs, or generated static assets.

Initial privacy/spam rules:

1. Do not add analytics, tracking scripts, or third-party embeds by default.
2. Keep public dog data limited to dog name, short description, neighborhood, and public photo URL.
3. Document that owners can request removal or correction of a submission.
4. If spam becomes a problem, consider adding a simple honeypot field, rate limiting at Caddy, or another low-dependency mitigation.

Do not build an admin interface yet.

For the first version, moderation can be handled manually with SQLite commands:

```sql
UPDATE dogs
SET status = 'approved', updated_at = strftime('%Y-%m-%dT%H:%M:%SZ', 'now')
WHERE id = ?;

UPDATE dogs
SET status = 'rejected', updated_at = strftime('%Y-%m-%dT%H:%M:%SZ', 'now')
WHERE id = ?;
```

Document this in `README.md`.

A future admin page can be added when the site needs it.

## Phase 9: Quality checks

Keep initial quality tooling simple:

1. Run `tsc --noEmit` for frontend type checking.
2. Run `vite build` to verify the frontend production build.
3. Manually test:
   - landing page dog loading
   - empty state
   - submission form validation
   - successful submission
   - upload size/type rejection
   - mobile layout
4. Check accessibility basics:
   - labels for all inputs
   - useful alt text for dog photos
   - keyboard-accessible navigation
   - readable color contrast

Optional later additions:

- Vitest for frontend utility tests
- Playwright for end-to-end tests
- PHP unit tests if backend logic grows

## Phase 10: Future extension points

The site should remain easy to extend without committing to large frameworks early.

Possible future additions:

- `/dog-parks`
- `/about`
- `/contact`
- admin moderation page
- image resizing and thumbnail generation
- automated database migrations
- automated backups
- object storage for images if local disk storage becomes limiting

Guiding rule: add dependencies only when they clearly remove more complexity than they introduce.
