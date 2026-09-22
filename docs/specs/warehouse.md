# Warehouse Specification

## Purpose

The warehouse module supports ERP sub-item master data, inventory lookup, inventory transaction history, and document-based inbound and outbound operations.

Phase 1 implements ERP sub-item master data import and lookup. Phase 2a implements inventory snapshots, manual inventory adjustments, inventory lookup with current stock, and inventory transaction history. Phase 2b implements document-based batch inbound and outbound operations. Stock requests and approval workflows are explicitly out of scope.

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

### Inbound and Outbound Documents

Inbound and outbound are independent routine operations with separate Filament navigation and permissions. Both use a document header with multiple part lines.

- The document number is entered manually and must be unique across both directions.
- Each document has one business date and one warehouse.
- Each line records a part, an optional location in the selected warehouse, and a positive quantity.
- The same part and location combination cannot appear twice in one document.
- Disabled parts and parts whose `計算庫存` value is false are still valid document lines and change inventory.
- Saving a new document posts every line immediately. There is no draft, request, approval, or partial-posting workflow.
- The whole document posts in one database transaction. A failure on any line rolls back the document, transactions, and inventory changes.
- Posted documents cannot be deleted. Header and line data may be edited; every edit creates a revision snapshot and appends only the net inventory differences as new transactions.
- Changing warehouse, part, or location reverses the old inventory contribution and applies the new contribution atomically.
- Business date and actual posting or edit timestamps are stored separately.

Do not allow stock quantity changes without an inventory transaction record.

Negative inventory is configurable through `WAREHOUSE_ALLOW_NEGATIVE_INVENTORY`; the default is to reject inventory-changing operations that would make stock negative.

Manual inventory adjustment remains available for adjustment, cycle count, and import correction only. Inbound and outbound transaction types must be created through documents.

### Inventory Query

Users can search by part number, name, barcode, category, accounting category, supplier, warehouse, location, disabled status, and stock tracking status.

The query result should show part identity, stock unit, warehouse, location, current stock, safety stock, recent purchase price, stock tracking status, and disabled status.

Inventory snapshots are updated by the inventory adjustment service and inbound or outbound documents.

### Transaction History

Users can filter history by part, warehouse, transaction type, date range, and reference document. Results should include occurred time, part, warehouse, location, transaction type, before quantity, quantity delta, after quantity, reference, and operator.

## Data Model

The active data model includes:

- `parts`
- `warehouses`
- `warehouse_locations`
- `suppliers`
- `import_batches`
- `import_batch_rows`
- `inventories`
- `inventory_transactions`
- `inventory_documents`
- `inventory_document_items`
- `inventory_document_revisions`

`inventory_document_revisions` stores an immutable snapshot after initial posting and every edit. Inventory transactions reference the source document and revision.

## Permissions

Use Filament Shield and Spatie Permission.

Warehouse resources use Filament Shield resource permissions as the authoritative permissions:

- `ViewAny:Part` allows viewing the part master list.
- `Create:Part` allows maintaining parts and using the `Import XLSX` action.
- `ViewAny:ImportBatch` and `View:ImportBatch` allow viewing import batch results and row errors.
- `ViewAny:Warehouse`, `Create:Warehouse`, `Update:Warehouse`, and `Delete:Warehouse` control warehouse maintenance.
- `ViewAny:Supplier`, `Create:Supplier`, `Update:Supplier`, and `Delete:Supplier` control supplier maintenance.
- `ViewAny:Inventory`, `View:Inventory`, `Create:Inventory`, and `Update:Inventory` control inventory lookup and manual adjustments.
- `ViewAny:InventoryTransaction` and `View:InventoryTransaction` control transaction history lookup.
- `ViewAny:InboundDocument`, `View:InboundDocument`, `Create:InboundDocument`, and `Update:InboundDocument` control inbound documents.
- `ViewAny:OutboundDocument`, `View:OutboundDocument`, `Create:OutboundDocument`, and `Update:OutboundDocument` control outbound documents.
- Every role, including `super_admin` and `admin`, must be granted each required resource permission explicitly. Role names do not bypass resource policies.

## Acceptance Criteria

Phase 1 acceptance criteria:

- Imported part count matches the unique source part numbers.
- Warehouse and supplier master data are created from source codes.
- Import failures preserve row number, raw payload, and error message.
- Unauthorized users cannot import or maintain warehouse master records.

Phase 2a acceptance criteria:

- Manual inventory adjustments create or update inventory snapshots and append transaction history.
- Inventory-changing operations create `inventory_transactions` before updating `inventories`.
- Negative inventory is rejected by default and allowed only when configured.
- Users can filter inventory lookup by part, warehouse, location, disabled status, and stock tracking status.
- Users can filter transaction history by part, warehouse, transaction type, date range, and reference.
- Unauthorized users cannot adjust inventory or view inventory transaction history.

Current Phase 2a implementation gaps:

- Inventory lookup does not yet expose barcode, product category, accounting category, or supplier filters in the inventory screen.
- Parts without an inventory snapshot are not shown as zero-stock inventory rows.

Phase 2b acceptance criteria:

- A multi-line inbound document atomically increases inventory and writes one transaction per affected inventory snapshot.
- A multi-line outbound document atomically decreases inventory and rejects the whole document when any resulting snapshot would be negative by default.
- Document numbers are globally unique and duplicate part-location lines are rejected.
- Editing a posted document preserves prior transactions, creates a revision snapshot, and writes only net inventory differences.
- Header-only edits create a revision without zero-delta inventory transactions.
- Inbound and outbound permissions are independent, and posted documents cannot be deleted.

## Pending Confirmation

- Meaning of `主要來源` values `P/S/M`.
- Unit for `單位淨重`.
- Whether barcode should be globally unique.
- Whether prices need currency, tax type, and effective dates.
