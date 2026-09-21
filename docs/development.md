# Development Guide

## Runtime

This Laravel project runs PHP through Docker. The project PHP version is provided by the `app` service, which is built from `docker/php/Dockerfile` using `php:8.5-fpm`.

Do not use the host machine `php`, `composer`, or `artisan` commands for project validation. The host PHP may be a different version and is not the source of truth.

## Services

The main services are defined in `docker-compose.yml`:

- `app`: PHP 8.5 FPM, Composer, Laravel runtime.
- `web`: Nginx, exposed at `http://localhost:8000`.
- `db`: MySQL 8.4, exposed at `127.0.0.1:33066`.
- `node`: Node 22 for Vite and frontend tooling.

Start the backend environment:

```sh
./env.sh start
```

Equivalent Docker command:

```sh
docker compose up -d app web db
```

Start the Node service only when frontend tooling is needed:

```sh
docker compose up -d node
```

## PHP and Composer Commands

Run PHP, Composer, Artisan, Pint, and tests inside the `app` container:

```sh
docker compose exec app php -v
docker compose exec app composer -V
docker compose exec app php artisan about
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose exec app ./vendor/bin/pint --test
docker compose exec app composer test
```

If dependencies need installation or refresh:

```sh
docker compose exec app composer install
```

## Frontend Commands

Run Node and Vite commands inside the `node` container:

```sh
docker compose exec node npm install
docker compose exec node npm run dev
docker compose exec node npm run build
```

## Verification Checklist

Before completing backend work, run the narrowest relevant checks:

- Syntax or framework sanity: `docker compose exec app php artisan about`
- Migrations touched: `docker compose exec app php artisan migrate`
- Tests touched: `docker compose exec app php artisan test`
- Formatting check: `docker compose exec app ./vendor/bin/pint --test`
- Frontend assets touched: `docker compose exec node npm run build`

If Docker is unavailable, report the exact failure and do not substitute host PHP.
