# Warehouse Specification

## Purpose

The warehouse module supports ERP sub-item master data, stock requests, outbound fulfillment, inventory lookup, and inventory transaction history.

Source references:

- `docs/functional flow chart.png`
- `docs/Warehouse Sub-item Data List.xlsx`
- `docs/archive/warehouse-repository-knowledge.md` historical analysis only

## Workflows

### Sub-item Master Maintenance

Users with warehouse management permission can import or maintain part master data. The Excel source contains 18,011 rows and 33 columns, including part number, name, barcode, unit, categories, warehouse, supplier, replenishment settings, prices, tariff rate, and net weight.

The part number is the primary business identifier. Unknown code meanings, such as `主要來源` values `P/S/M`, must remain as source codes until confirmed.

### Stock Request

Requesters create stock requests with one or more requested parts. Each item records part, warehouse, optional location, requested quantity, fulfilled quantity, and memo.

Supported statuses:

- `draft`
- `submitted`
- `approved`
- `rejected`
- `fulfilled`
- `cancelled`

### Outbound Fulfillment

Warehouse operators fulfill approved requests. Fulfillment must check available inventory, update inventory snapshots, and create transaction history in a single database transaction.

Do not allow stock quantity changes without an inventory transaction record.

### Inventory Query

Users can search by part number, name, barcode, category, accounting category, supplier, warehouse, location, disabled status, and stock tracking status.

The query result should show part identity, stock unit, primary warehouse, supplier, current stock, safety stock, recent purchase price, and disabled status.

### Transaction History

Users can filter history by part, warehouse, transaction type, date range, and reference document. Results should include occurred time, part, warehouse, location, transaction type, before quantity, quantity delta, after quantity, reference, and operator.

## Data Model

The active initial data model includes:

- `parts`
- `warehouses`
- `warehouse_locations`
- `suppliers`
- `inventories`
- `inventory_transactions`
- `stock_requests`
- `stock_request_items`
- `import_batches`
- `import_batch_rows`

## Permissions

Use Filament Shield and Spatie Permission.

Suggested permissions:

- `warehouse.viewer`
- `warehouse.requester`
- `warehouse.operator`
- `warehouse.manager`
- `admin`

## Acceptance Criteria

- Imported part count matches the unique source part numbers.
- Warehouse and supplier master data are created from source codes.
- A request can be created, approved, fulfilled, and queried.
- Fulfillment updates `inventories` and writes `inventory_transactions`.
- Import failures preserve row number, raw payload, and error message.
- Unauthorized users cannot import, adjust, fulfill, or approve warehouse records.

## Pending Confirmation

- Meaning of `主要來源` values `P/S/M`.
- Unit for `單位淨重`.
- Whether outbound can create negative inventory.
- Whether stock requests require multi-step approval.
- Whether barcode should be globally unique.
- Whether prices need currency, tax type, and effective dates.
