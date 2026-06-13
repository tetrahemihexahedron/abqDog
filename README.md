# albuquerque.dog

## Local development

This project uses `just` for common development commands. The devcontainers install it automatically.

Start the development environment:

```sh
just dev
```

Then open <http://localhost:8080>.

The development setup runs the Vite frontend dev server behind Caddy and bind-mounts the frontend and backend source directories for local editing. To stop the containers, press `Ctrl+C`, or run:

```sh
just down
```

List available commands:

```sh
just --list
```

## Development database

Create the local `.env` file at the repository root from the tracked sample:

```sh
just env-init
```

Edit `.env` if local values need to differ from the defaults.

Reset the development database and load the fake pet squash seed data:

```sh
just db-reset
```

Other useful database commands:

```sh
just db-init   # initialize schema only
just db-seed   # load development seed data
just db-check  # verify PDO can query the database
just db-shell  # open sqlite shell
```
