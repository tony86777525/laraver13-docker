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

## Inventory Documents

Routine inbound and outbound operations use one shared document model:

- `inventory_documents`: direction, globally unique document number, business date, warehouse, current revision, operator, and posting timestamps.
- `inventory_document_items`: the current multi-line part, optional location, and positive quantity state.
- `inventory_document_revisions`: immutable JSON snapshots of the document after posting and each edit.

New documents post immediately. Editing compares old and new inventory contributions, locks all affected snapshots in stable key order, appends net-difference transactions, and updates current snapshots atomically. Documents are not deleted.

`inventory_transactions` references the source inventory document and revision while retaining its own part, warehouse, location, quantity, document number, operator, and occurred-time snapshot.

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
