# Backend Architecture

## Framework and Runtime

This project uses Laravel 13 with PHP 8.5 in the Docker `app` service. Apply modern PHP typing where it improves clarity, while staying compatible with the installed Laravel and package versions in `composer.lock`.

## Layering

Use the following responsibilities:

- Controller: HTTP input, authorization, validation, and response shaping.
- Service: use cases, workflow decisions, transaction boundaries, and business rules.
- Repository: query construction and persistence operations.
- Model: Eloquent mapping, relationships, casts, scopes, and data-level invariants.
- Policy or permission layer: authorization through Filament Shield and Spatie Permission.

Keep controllers thin. Do not place warehouse workflow decisions directly in controllers or Filament resource actions.

## Repository Rules

Repositories should encapsulate reusable query and persistence operations, but should not duplicate Eloquent behavior without reason.

Use repositories for:

- Search and filter queries with multiple optional criteria.
- Locking rows for inventory-changing workflows.
- Upsert or import persistence operations.
- Querying transaction history.

Do not build SQL through string concatenation. Use Eloquent, the query builder, parameter binding, and whitelisted column mappings for dynamic ordering or filtering.

## Service Rules

Services own workflow consistency:

- Wrap inventory-changing operations in database transactions.
- Create inventory transaction records before updating the inventory snapshot.
- Keep import error handling explicit and row-level.
- Surface unconfirmed business rules as validation or specification questions, not hard-coded assumptions.

## Naming

Use clear domain names:

- Repositories: `PartRepository`, `InventoryRepository`, `StockRequestRepository`.
- Services: `PartImportService`, `StockFulfillmentService`, `InventoryQueryService`.
- Tests: feature tests for workflows, unit tests for isolated service rules.

## Validation and Authorization

Validate external input before calling services. Check authorization at the HTTP or Filament action boundary, then let services enforce domain invariants that must hold regardless of entrypoint.

## Filament Resource Permissions

Use Filament Shield generated resource permissions as the single source of truth for Filament resource access, for example `ViewAny:Part`, `View:Part`, `Create:Part`, `Update:Part`, and `Delete:Part`.

Do not add parallel business permission names for the same Filament resource, such as `warehouse.viewer` or `warehouse.manager`, unless a documented mapping layer is introduced and tested. Mixing Shield resource permissions with separate domain permissions makes the role editor appear correct while policies still return 403.

Filament navigation groups must not have the exact same label as a resource model label in that group. Use a distinct group label such as `Warehouse Admin` when a `Warehouse` resource also exists.
