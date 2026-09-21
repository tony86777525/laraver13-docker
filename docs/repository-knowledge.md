# ERP 倉儲系統 Repository Knowledge

## 1. 文件目的

本文件整理倉儲系統的作業流程、資料模型與 Repository 分層設計，供後續 Laravel 13 + PHP 8.5 開發 migration、Model、Repository、Service、Filament 管理介面與測試時使用。

參考來源：

- `docs/functional flow chart.png`：進出庫與子料管理功能流程圖。
- `docs/Warehouse Sub-item Data List.xlsx`：子件一覽資料，共 18,011 筆、33 欄。

本文件只定義知識與資料表設計，不包含程式碼實作。

## 2. 系統流程概覽

倉儲模組以「品項主檔、庫存餘額、庫存異動」為核心，支援下列流程：

1. **申請作業**：使用者建立出庫或領料申請，選擇子件、數量、倉庫與用途。
2. **出庫作業**：倉管依申請單確認可用庫存，完成扣庫並產生異動紀錄。
3. **補料子件資料維護**：匯入或維護子件主檔、供應商、倉庫、補貨參數與價格資料。
4. **子料查詢**：依品號、品名、分類、供應商、倉庫、儲位、失效狀態查詢子件。
5. **倉庫異動紀錄**：記錄入庫、出庫、調整、匯入修正與盤點差異，作為庫存追溯來源。

核心原則：`inventories` 儲存目前庫存快照，`inventory_transactions` 儲存不可刪除的異動流水帳。任何會改變庫存的流程，都必須先寫入異動紀錄，再更新庫存餘額。

## 3. Excel 欄位映射

| Excel 欄位 | 建議落點 | 說明 |
| --- | --- | --- |
| 品號 | `parts.part_no` | 唯一品號，必填。 |
| 品名 | `parts.name` | 中文品名，必填。 |
| 條碼編號 | `parts.barcode` | 可空，允許未來建立唯一索引前先清理重複值。 |
| 庫存單位 | `parts.stock_unit` | 如 `PCS`、`KG`、`組`。 |
| 商品分類 | `parts.product_category_code` | 保留原始分類代碼。 |
| 會計分類 | `parts.accounting_category_code` | 保留原始會計分類代碼。 |
| 計算庫存 | `parts.is_stock_tracked` | `T/F` 轉 boolean。 |
| 主要倉庫 | `warehouses.code` | 倉庫代碼，如 `WHPT`。 |
| 庫別名稱 | `warehouses.name` | 倉庫名稱，如 `物料倉`。 |
| 主要來源 | `parts.source_type` | 保留 `P/S/M` 原值，中文意義待業務確認。 |
| 主供應商 | `suppliers.code` | 供應商代碼。 |
| 廠商簡稱 | `suppliers.short_name` | 供應商簡稱。 |
| 循環盤點碼 | `parts.cycle_count_code` | 可空，供盤點排程使用。 |
| 儲位 | `warehouse_locations.code` | 可空，Excel 僅少量資料有值。 |
| 失效 | `parts.is_disabled` | `T/F` 轉 boolean。 |
| 依需求補貨 | `parts.replenish_on_demand` | `T/F` 轉 boolean。 |
| 前置天數 | `parts.lead_time_days` | 整數，補貨估算使用。 |
| 安全存量 | `parts.safety_stock_qty` | decimal，低於此數量需提示。 |
| 最低補量 | `parts.minimum_reorder_qty` | decimal。 |
| 補貨倍量 | `parts.reorder_multiple_qty` | decimal。 |
| 標準進價 | `parts.standard_purchase_price` | decimal。 |
| 最近進價 | `parts.last_purchase_price` | decimal。 |
| 零售價 | `parts.retail_price` | decimal。 |
| 定價一～四 | `parts.price_level_1`～`price_level_4` | decimal。 |
| 低階碼 | `parts.low_level_code` | BOM 或階層用途，先保留代碼。 |
| 商品描述 | `parts.description` | 中文描述。 |
| 英文品名 | `parts.english_name` | 英文品名。 |
| 英文描述 | `parts.english_description` | 英文描述。 |
| 進口關稅率 | `parts.import_tariff_rate` | decimal，例：`0.05`。 |
| 單位淨重 | `parts.unit_net_weight` | decimal，單位待確認。 |

## 4. 資料表設計草案

### `parts`

子件與品項主檔。`part_no` 唯一且不可重複，名稱、分類、單位、來源、補貨、價格與描述都集中於此表。

建議欄位：

- `id`
- `part_no`
- `name`
- `barcode`
- `stock_unit`
- `product_category_code`
- `accounting_category_code`
- `source_type`
- `cycle_count_code`
- `low_level_code`
- `description`
- `english_name`
- `english_description`
- `is_stock_tracked`
- `is_disabled`
- `replenish_on_demand`
- `lead_time_days`
- `safety_stock_qty`
- `minimum_reorder_qty`
- `reorder_multiple_qty`
- `standard_purchase_price`
- `last_purchase_price`
- `retail_price`
- `price_level_1`
- `price_level_2`
- `price_level_3`
- `price_level_4`
- `import_tariff_rate`
- `unit_net_weight`
- `supplier_id`
- `primary_warehouse_id`
- `timestamps`

索引：

- unique：`part_no`
- index：`barcode`
- index：`product_category_code`
- index：`accounting_category_code`
- index：`supplier_id`
- index：`primary_warehouse_id`
- index：`is_disabled`

### `warehouses`

倉庫主檔，保存 Excel 的主要倉庫與庫別名稱。

建議欄位：

- `id`
- `code`
- `name`
- `is_active`
- `timestamps`

索引：

- unique：`code`

### `warehouse_locations`

倉庫內儲位。Excel 中儲位資料很少，但仍應正規化，避免未來直接塞入庫存表造成重複字串。

建議欄位：

- `id`
- `warehouse_id`
- `code`
- `name`
- `is_active`
- `timestamps`

索引：

- unique：`warehouse_id`, `code`

### `suppliers`

供應商主檔。

建議欄位：

- `id`
- `code`
- `short_name`
- `name`
- `is_active`
- `timestamps`

索引：

- unique：`code`
- index：`short_name`

### `inventories`

目前庫存快照。每個品項在每個倉庫與儲位只保留一筆目前數量。

建議欄位：

- `id`
- `part_id`
- `warehouse_id`
- `warehouse_location_id`
- `quantity_on_hand`
- `quantity_reserved`
- `last_transaction_at`
- `timestamps`

索引：

- unique：`part_id`, `warehouse_id`, `warehouse_location_id`
- index：`warehouse_id`
- index：`part_id`

### `inventory_transactions`

庫存異動流水帳。此表是庫存追溯的主要來源，原則上不允許硬刪除。

建議欄位：

- `id`
- `part_id`
- `warehouse_id`
- `warehouse_location_id`
- `transaction_type`
- `quantity_delta`
- `quantity_before`
- `quantity_after`
- `reference_type`
- `reference_id`
- `memo`
- `occurred_at`
- `created_by`
- `timestamps`

`transaction_type` 建議值：

- `inbound`
- `outbound`
- `adjustment`
- `cycle_count`
- `import_correction`

索引：

- index：`part_id`, `occurred_at`
- index：`warehouse_id`, `occurred_at`
- index：`transaction_type`
- index：`reference_type`, `reference_id`

### `stock_requests`

出庫或領料申請單主檔。

建議欄位：

- `id`
- `request_no`
- `request_type`
- `status`
- `requested_by`
- `approved_by`
- `approved_at`
- `fulfilled_by`
- `fulfilled_at`
- `memo`
- `timestamps`

`status` 建議值：

- `draft`
- `submitted`
- `approved`
- `rejected`
- `fulfilled`
- `cancelled`

索引：

- unique：`request_no`
- index：`status`
- index：`requested_by`

### `stock_request_items`

申請單明細。

建議欄位：

- `id`
- `stock_request_id`
- `part_id`
- `warehouse_id`
- `warehouse_location_id`
- `requested_quantity`
- `fulfilled_quantity`
- `memo`
- `timestamps`

索引：

- index：`stock_request_id`
- index：`part_id`

### `import_batches`

Excel 匯入批次主檔。

建議欄位：

- `id`
- `source_filename`
- `source_hash`
- `import_type`
- `status`
- `total_rows`
- `success_rows`
- `failed_rows`
- `started_at`
- `finished_at`
- `created_by`
- `timestamps`

### `import_batch_rows`

Excel 匯入逐列結果，保留原始列資料與錯誤訊息，方便追查資料清理問題。

建議欄位：

- `id`
- `import_batch_id`
- `row_number`
- `raw_payload`
- `status`
- `error_message`
- `part_id`
- `timestamps`

## 5. Repository 與 Service 分層

### Repository

Repository 只處理查詢與持久化，不放流程決策。

建議類別：

- `PartRepository`
  - 依品號查詢。
  - 依條碼、品名、分類、供應商、失效狀態搜尋。
  - 匯入時執行 upsert。
- `InventoryRepository`
  - 查詢目前庫存。
  - 鎖定指定品項與倉庫庫存列。
  - 更新 `quantity_on_hand` 與 `quantity_reserved`。
- `InventoryTransactionRepository`
  - 建立異動紀錄。
  - 查詢品項、倉庫、申請單的異動歷史。
- `StockRequestRepository`
  - 建立與查詢申請單。
  - 更新申請單狀態。
- `ImportBatchRepository`
  - 建立匯入批次。
  - 記錄每列匯入結果。

### Service

Service 負責交易邊界與業務規則。

建議類別：

- `PartImportService`
  - 解析 Excel 欄位。
  - 將 `T/F` 轉 boolean。
  - 建立或更新 `parts`、`warehouses`、`suppliers`、`warehouse_locations`。
  - 記錄 `import_batches` 與 `import_batch_rows`。
- `StockRequestService`
  - 建立申請單。
  - 審核、駁回、取消申請單。
- `StockFulfillmentService`
  - 檢查可用庫存。
  - 以資料庫交易完成扣庫。
  - 建立 `inventory_transactions`。
- `InventoryQueryService`
  - 組合品項、倉庫、儲位、庫存餘額與最近異動供查詢畫面使用。

## 6. 權限與操作角色

專案已安裝 Filament、Filament Shield 與 Spatie Permission，倉儲模組應沿用現有權限機制。

建議角色：

- `warehouse.viewer`：查詢子件、庫存與異動紀錄。
- `warehouse.requester`：建立出庫或領料申請。
- `warehouse.operator`：執行出庫、入庫、調整與盤點。
- `warehouse.manager`：審核申請與匯入主檔。
- `admin`：管理所有資料與權限。

重要操作應記錄 `created_by` 或流程操作者欄位，不只依賴資料庫 timestamps。

## 7. 匯入規則

匯入 `Warehouse Sub-item Data List.xlsx` 時，應以 `品號` 作為主鍵比對來源。

基本規則：

- `品號`、`品名`、`庫存單位` 必填。
- `計算庫存`、`失效`、`依需求補貨` 僅接受 `T`、`F`。
- 數量、價格、關稅率、淨重欄位應轉為 decimal。
- `主要倉庫` 不存在時建立 `warehouses`。
- `主供應商` 不存在時建立 `suppliers`。
- 同一列匯入失敗時，不應中斷整個批次；錯誤寫入 `import_batch_rows.error_message`。
- 匯入完成後更新 `import_batches.success_rows` 與 `failed_rows`。

若匯入會改變既有庫存數量，不應直接覆蓋 `inventories`。必須使用 `inventory_transactions.transaction_type = import_correction` 追溯修正原因。

## 8. 主要查詢情境

### 子料查詢

輸入條件：

- 品號
- 品名
- 條碼
- 商品分類
- 會計分類
- 供應商
- 倉庫
- 儲位
- 是否失效
- 是否計算庫存

輸出欄位：

- 品號
- 品名
- 庫存單位
- 主要倉庫
- 供應商
- 目前庫存
- 安全存量
- 最近進價
- 失效狀態

### 倉庫異動紀錄

輸入條件：

- 品號
- 倉庫
- 異動類型
- 起訖日期
- 申請單號

輸出欄位：

- 異動時間
- 品號與品名
- 倉庫與儲位
- 異動類型
- 異動前數量
- 異動數量
- 異動後數量
- 來源單據
- 操作者

## 9. 待業務確認事項

以下資訊無法從流程圖與 Excel 可靠判斷，實作前需確認：

- `主要來源` 的 `P/S/M` 中文定義與是否需要獨立代碼表。
- `低階碼` 是否來自 BOM 階層，是否需要與生產或採購模組整合。
- `單位淨重` 的單位。
- 出庫申請是否需要多段簽核。
- 出庫是否允許超領或負庫存。
- 條碼是否應全域唯一，或只在同品項/同倉庫內唯一。
- 價格欄位是否需幣別、稅別與生效日期。
- 盤點流程是否需要凍結庫存或建立盤點單。

## 10. 驗收檢查

後續實作完成時，至少應通過下列情境：

1. 匯入 Excel 後，`parts.part_no` 筆數與來源唯一品號數一致。
2. `warehouses` 能由主要倉庫與庫別名稱建立唯一倉庫。
3. `suppliers` 能由主供應商與廠商簡稱建立唯一供應商。
4. 建立出庫申請後，申請單與明細可被查詢。
5. 完成出庫後，`inventories.quantity_on_hand` 正確扣減。
6. 每次庫存變更都能在 `inventory_transactions` 查到異動前、異動數量、異動後。
7. 查詢失效品項時，`parts.is_disabled` 能正確篩選。
8. 低於安全存量的品項能被查出。
9. 匯入失敗列會保留原始資料與錯誤訊息。
10. 未授權使用者無法進行出庫、調整或匯入。
