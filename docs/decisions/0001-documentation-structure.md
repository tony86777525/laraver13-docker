# 0001: Documentation Structure

## Status

Accepted

## Context

The project is moving from a Laravel skeleton toward an ERP warehouse system. The team needs project guidance that Codex and human contributors can follow without placing every detail in `AGENTS.md`.

## Decision

Use this documentation structure:

- `docs/development.md` for environment setup and verification commands.
- `docs/architecture/` for long-lived technical architecture.
- `docs/specs/` for feature behavior and acceptance criteria.
- `docs/decisions/` for architecture decision records.

Keep root `AGENTS.md` as a short navigation and working-rules file.

## Consequences

- Contributors can quickly find the right source of truth.
- Feature behavior changes must update specs.
- Architecture changes must update architecture docs or decision records.
- `AGENTS.md` stays concise and avoids becoming a large requirements document.
