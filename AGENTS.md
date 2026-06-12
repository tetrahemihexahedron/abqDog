## Project description

This project contains the code for a website albuquerque.dog, which has pictures and short descriptions of dogs that live in Albuquerque, New Mexico. People can submit their dogs for possible inclusion via a short form. There is a small database containing the submitted information about dogs.

To begin with, the website will only have

- a landing page, containing the dogs that are showcased;
- a page with a form for submitting information about a dog.

The website will be deployed using caddy running in a docker container.

## Conventions

- Generally, specify the versions of software used. Don't use 'latest' for a Docker image tag.

### Dockerfiles and compose files

- While build processes can be run as root, prefer to run long-running processes as a non-root user. The UID and GID of this user should be specified in build args, with default value 1001.

- Give the versions of images and major installed software in build args, with defaults set in the Dockerfile.

- If there is a build process, use a multi-stage Dockerfile and copy only the built assets into the final stage.

- Prefer using the same Dockerfile for production and development, using an environment variable as a switch if needed, say, to determine whether to install dev dependencies in a final image.

- Use a base compose.yml file that is appropriate for production and an override file, compose.dev.yml, with changes that are appropriate for development. In particular, the application code should be mounted in the compose.dev.yml file.
