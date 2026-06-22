# albuquerque.dog

## About this project

Inspired by [Dogs of Dev](https://dogsof.dev), I want albuquerque.dog to be a small website showcasing dogs in Albuquerque, New Mexico, who are friends of [Rosie the Dog](https://rosiethe.dog). A dog's human can submit their dog for possible inclusion via a small form.

This project is an experiment in agentic coding.

I am running [Pi](https://pi.dev) in a [devcontainer](https://containers.dev), and I'm giving the agent free range within the container. I am using gpt-5.5, mostly with a 'medium' thinking level, via my ChatGPT+ subscription. The agent is writing almost all of the code, but I am playing a very active role. I am working incrementally. I ask the agent to produce a plan before writing the code, read everything and often ask the agent to make changes and to consider different design choices.

The ideal outcome is that I produce maintainable and extendible code that I understand and trust at least as much as code I would write myself, in much less time than it would take me to write it.

A secondary goal is to improve my software engineering skills and judgment.

This section of the README was written by a living breathing person, but the rest of this README is AI-generated.

## Local development

This project uses [`just`](https://just.systems) for common development commands. These commands will work inside the devcontainer. They should also work locally, without running the devcontainer, if `just` and other dependencies (like Docker) are installed.

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
