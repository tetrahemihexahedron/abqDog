# albuquerque.dog

## Local development with Docker Compose

Run the development containers with the base Compose file plus the development override:

```sh
docker compose -f compose.yml -f compose.dev.yml up --build
```

Then open <http://localhost:8080>.

The development setup runs the Vite frontend dev server behind Caddy and bind-mounts the frontend and backend source directories for local editing. To stop the containers, press `Ctrl+C`, or run:

```sh
docker compose -f compose.yml -f compose.dev.yml down
```
