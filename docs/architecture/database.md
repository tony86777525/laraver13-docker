# Database Architecture

## Database Runtime

The local database service is MySQL 8.4 through Docker `db`. Laravel commands that read or mutate the schema must run inside Docker `app`.

```sh
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:status
```

## Schema Principles

- Use migrations as the source of truth for schema changes.
- Prefer explicit foreign keys for domain relationships.
- Add indexes for frequent search, filter, and history queries.
- Use decimal columns for quantities, prices, tariffs, and weights.
- Use booleans for imported `T/F` fields after normalization.
- Preserve original external codes when business meaning is not yet confirmed.

## Inventory Model

Warehouse inventory uses two complementary records:

- `inventories`: current quantity snapshot per part, warehouse, and location.
- `inventory_transactions`: append-only transaction history for inbound, outbound, adjustment, cycle count, and import correction events.

Any operation that changes stock must happen in a database transaction and must produce an `inventory_transactions` record.

## Import Model

Excel imports should keep batch and row-level traceability:

- `import_batches`: source file, import type, status, counts, timestamps, and creator.
- `import_batch_rows`: row number, raw payload, status, error message, and linked part.

Failed rows should not stop the entire batch unless the file format itself is unreadable.

## Migration Naming

Use Laravel timestamped migration names with clear intent, for example:

```text
create_parts_table
create_warehouses_table
create_inventory_transactions_table
```

Keep destructive schema changes separate from additive changes.
