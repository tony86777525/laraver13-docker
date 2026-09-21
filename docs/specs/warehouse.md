# Warehouse Specification

## Purpose

The warehouse module supports ERP sub-item master data, stock requests, outbound fulfillment, inventory lookup, and inventory transaction history.

Phase 1 implements ERP sub-item master data import and lookup only. Stock requests, approvals, outbound fulfillment, inventory snapshots, and inventory transaction history remain future scope.

Source references:

- `docs/functional flow chart.png`
- `docs/Warehouse Sub-item Data List.xlsx`
- `docs/archive/warehouse-repository-knowledge.md` historical analysis only

## Workflows

### Sub-item Master Maintenance

Users with warehouse management permission can import or maintain part master data. The Excel source contains 18,011 rows and 33 columns, including part number, name, barcode, unit, categories, warehouse, supplier, replenishment settings, prices, tariff rate, and net weight.

The part number is the primary business identifier. Unknown code meanings, such as `主要來源` values `P/S/M`, must remain as source codes until confirmed.

Phase 1 imports the 33 columns from `docs/Warehouse Sub-item Data List.xlsx` exactly as the accepted source format:

- `品號`
- `品名`
- `條碼編號`
- `庫存單位`
- `商品分類`
- `會計分類`
- `計算庫存`
- `主要倉庫`
- `庫別名稱`
- `主要來源`
- `主供應商`
- `廠商簡稱`
- `循環盤點碼`
- `儲位`
- `失效`
- `依需求補貨`
- `前置天數`
- `安全存量`
- `最低補量`
- `補貨倍量`
- `標準進價`
- `最近進價`
- `零售價`
- `定價一`
- `定價二`
- `定價三`
- `定價四`
- `低階碼`
- `商品描述`
- `英文品名`
- `英文描述`
- `進口關稅率`
- `單位淨重`

The import must reject files whose header row does not exactly match this list. Row-level data errors must be recorded with row number, raw payload, and error message without stopping the rest of the batch.

`計算庫存`, `失效`, and `依需求補貨` import `T/F` values as booleans. Decimal fields preserve numeric `0` as `0`; blank strings import as `null`.

Phase 1 XLSX uploads support files up to 50MB through the Docker Nginx/PHP upload limits. Larger files require raising the Docker upload settings or moving the import workflow to a queued/background process.

### Stock Request

Not implemented in Phase 1.

Requesters create stock requests with one or more requested parts. Each item records part, warehouse, optional location, requested quantity, fulfilled quantity, and memo.

Supported statuses:

- `draft`
- `submitted`
- `approved`
- `rejected`
- `fulfilled`
- `cancelled`

### Outbound Fulfillment

Not implemented in Phase 1.

Warehouse operators fulfill approved requests. Fulfillment must check available inventory, update inventory snapshots, and create transaction history in a single database transaction.

Do not allow stock quantity changes without an inventory transaction record.

The negative inventory strategy is not implemented in Phase 1. A future implementation should make this policy configurable instead of hard-coding one behavior.

### Inventory Query

Users can search by part number, name, barcode, category, accounting category, supplier, warehouse, location, disabled status, and stock tracking status.

The query result should show part identity, stock unit, primary warehouse, supplier, current stock, safety stock, recent purchase price, and disabled status.

Phase 1 query results do not show current stock because `inventories` is not created until inventory-changing workflows are implemented.

### Transaction History

Not implemented in Phase 1.

Users can filter history by part, warehouse, transaction type, date range, and reference document. Results should include occurred time, part, warehouse, location, transaction type, before quantity, quantity delta, after quantity, reference, and operator.

## Data Model

The Phase 1 active data model includes:

- `parts`
- `warehouses`
- `warehouse_locations`
- `suppliers`
- `import_batches`
- `import_batch_rows`

Future phases are expected to add:

- `inventories`
- `inventory_transactions`
- `stock_requests`
- `stock_request_items`

## Permissions

Use Filament Shield and Spatie Permission.

Phase 1 uses Filament Shield resource permissions as the authoritative permissions:

- `ViewAny:Part` allows viewing the part master list.
- `Create:Part` allows maintaining parts and using the `Import XLSX` action.
- `ViewAny:ImportBatch` and `View:ImportBatch` allow viewing import batch results and row errors.
- `ViewAny:Warehouse`, `Create:Warehouse`, `Update:Warehouse`, and `Delete:Warehouse` control warehouse maintenance.
- `ViewAny:Supplier`, `Create:Supplier`, `Update:Supplier`, and `Delete:Supplier` control supplier maintenance.
- `super_admin` is the privileged admin role and bypasses resource policies.

Future phases are expected to add requester/operator permissions when request and fulfillment workflows are implemented.

## Acceptance Criteria

Phase 1 acceptance criteria:

- Imported part count matches the unique source part numbers.
- Warehouse and supplier master data are created from source codes.
- Import failures preserve row number, raw payload, and error message.
- Unauthorized users cannot import or maintain warehouse master records.

Future workflow acceptance criteria:

- A request can be created, approved, fulfilled, and queried.
- Fulfillment updates `inventories` and writes `inventory_transactions`.
- Unauthorized users cannot adjust, fulfill, or approve warehouse records.

## Pending Confirmation

- Meaning of `主要來源` values `P/S/M`.
- Unit for `單位淨重`.
- Whether outbound can create negative inventory; user direction is to make this configurable in a future phase.
- Whether stock requests require multi-step approval; approval workflow is not implemented in Phase 1.
- Whether barcode should be globally unique.
- Whether prices need currency, tax type, and effective dates.
