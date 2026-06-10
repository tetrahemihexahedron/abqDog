# Phase 1 Docker Compose and deployment setup

These instructions add the Compose and deployment files for the Phase 1 project skeleton. Run these after completing:

1. `notes/AI_plans/frontend_setup.md`
2. `notes/AI_plans/backend_setup.md`

## 0. Start from the repository root

Run all commands from the project root:

```sh
cd /workspaces/abqDog
```

Optional sanity check:

```sh
pwd
find . -maxdepth 2 -type f | sort
```

Expected files from the frontend and backend setup include:

- `frontend/package.json`
- `frontend/pnpm-lock.yaml`
- `frontend/Dockerfile`
- `backend/composer.json`
- `backend/composer.lock`
- `backend/public/index.php`
- `Dockerfile.backend`

## 1. Create deployment configuration files

Use explicit image versions in Compose:

- Caddy image: `caddy:2.10.2-alpine`

Create the deployment directory:

```sh
mkdir -p deploy
```

Create a basic PHP configuration file:

```sh
cat > deploy/php.ini <<'INI'
upload_max_filesize = 5M
post_max_size = 6M
memory_limit = 128M
max_execution_time = 30
INI
```

Create an initial Caddyfile:

```sh
cat > deploy/Caddyfile <<'CADDY'
:80 {
	root * /srv/www

	handle /api/* {
		php_fastcgi backend:9000 {
			root /var/www/backend/public
		}
	}

	handle /uploads/dogs/* {
		root * /uploads
		file_server
	}

	try_files {path} /index.html
	file_server
}
CADDY
```

## 2. Create `docker-compose.yml`

Create `docker-compose.yml`:

```sh
cat > docker-compose.yml <<'YAML'
services:
  backend:
    build:
      context: .
      dockerfile: Dockerfile.backend
    environment:
      DATABASE_PATH: /data/albuquerque-dog.sqlite
      UPLOAD_DIR: /uploads/dogs
      PUBLIC_UPLOAD_BASE: /uploads/dogs
    volumes:
      - sqlite-data:/data
      - uploaded-images:/uploads
      - ./deploy/php.ini:/usr/local/etc/php/conf.d/zz-abqdog.ini:ro

  frontend-build:
    build:
      context: ./frontend
      dockerfile: Dockerfile
    user: "0:0"
    volumes:
      - frontend-dist:/dist
    command: ["sh", "-c", "rm -rf /dist/* && cp -r /app/dist/. /dist"]

  caddy:
    image: caddy:2.10.2-alpine
    depends_on:
      backend:
        condition: service_started
      frontend-build:
        condition: service_completed_successfully
    ports:
      - "8080:80"
    volumes:
      - ./deploy/Caddyfile:/etc/caddy/Caddyfile:ro
      - frontend-dist:/srv/www:ro
      - uploaded-images:/uploads:ro

volumes:
  sqlite-data:
  uploaded-images:
  frontend-dist:
YAML
```

## 3. Verify the Compose setup

Build and start Docker services:

```sh
docker compose up --build
```

In a second terminal, verify Caddy serves the frontend:

```sh
curl -I http://localhost:8080
```

Verify Caddy reaches the backend placeholder through the API route:

```sh
curl http://localhost:8080/api/health
```

For the Phase 1 placeholder backend, any API path may return:

```json
{"ok":true}
```

Stop the Docker services:

```sh
docker compose down
```

## Compose/deployment completion checklist

- `deploy/php.ini` exists.
- `deploy/Caddyfile` exists.
- `docker-compose.yml` exists.
- Compose builds `frontend/Dockerfile` and `Dockerfile.backend`.
- Caddy uses the pinned image `caddy:2.10.2-alpine`.
- The site responds on `http://localhost:8080`.
- The backend placeholder responds through Caddy under `/api/*`.
