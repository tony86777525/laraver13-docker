# Frontend Architecture

## Stack

This project uses Laravel Blade, Vite, Tailwind CSS, and Filament. Node commands run in the Docker `node` service.

## Responsibilities

- Blade templates define semantic structure and server-rendered output.
- CSS controls visual presentation.
- JavaScript controls behavior and state transitions.
- Filament resources and pages should stay consistent with Filament conventions.

Avoid inline styles, inline event handlers, and Blade interpolation inside executable JavaScript. Pass server data through safe HTML attributes or JSON endpoints.

## Asset Commands

Use Docker for frontend commands:

```sh
docker compose up -d node
docker compose exec node npm install
docker compose exec node npm run dev
docker compose exec node npm run build
```

## UI Guidelines

- Prefer predictable admin workflows over decorative layouts.
- Use table filters, actions, forms, and relation managers for ERP-style tasks.
- Keep warehouse screens dense but readable for repeated operational use.
- Show status, validation errors, and import failures near the affected record or row.

## Change Rules

When frontend behavior changes, update the corresponding feature spec in `docs/specs/`. When shared layout or interaction patterns change, update this document.
