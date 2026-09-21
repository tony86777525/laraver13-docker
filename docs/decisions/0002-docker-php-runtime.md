# 0002: Docker PHP Runtime

## Status

Accepted

## Context

The project requires PHP 8.5. The correct PHP runtime is provided by the Docker `app` service, built from `docker/php/Dockerfile` with `php:8.5-fpm`.

The host machine may have a different or broken PHP installation and must not be used as the project runtime source of truth.

## Decision

Run PHP, Composer, Artisan, Pint, and tests inside Docker:

```sh
docker compose exec app php -v
docker compose exec app composer -V
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
```

Run frontend tooling inside Docker `node`:

```sh
docker compose exec node npm run build
```

## Consequences

- Documentation and agent instructions must use Docker commands.
- If Docker is unavailable, contributors should report the failure instead of falling back to host PHP.
- Version compatibility should be confirmed from `composer.json`, `composer.lock`, Docker configuration, and container output.
