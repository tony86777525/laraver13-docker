<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Part;
use App\Models\User;
use App\Repositories\ImportBatchRepository;
use App\Repositories\PartRepository;
use App\Repositories\SupplierRepository;
use App\Repositories\WarehouseRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

class PartImportService
{
    public const EXPECTED_HEADERS = [
        '品號',
        '品名',
        '條碼編號',
        '庫存單位',
        '商品分類',
        '會計分類',
        '計算庫存',
        '主要倉庫',
        '庫別名稱',
        '主要來源',
        '主供應商',
        '廠商簡稱',
        '循環盤點碼',
        '儲位',
        '失效',
        '依需求補貨',
        '前置天數',
        '安全存量',
        '最低補量',
        '補貨倍量',
        '標準進價',
        '最近進價',
        '零售價',
        '定價一',
        '定價二',
        '定價三',
        '定價四',
        '低階碼',
        '商品描述',
        '英文品名',
        '英文描述',
        '進口關稅率',
        '單位淨重',
    ];

    public function __construct(
        private readonly PartRepository $parts,
        private readonly WarehouseRepository $warehouses,
        private readonly SupplierRepository $suppliers,
        private readonly ImportBatchRepository $batches,
    ) {}

    public function import(string $filePath, ?string $sourceFilename = null, ?User $creator = null, ?string $storedPath = null): ImportBatch
    {
        $batch = $this->batches->createWarehousePartBatch($sourceFilename, $storedPath, $creator);
        $this->batches->markProcessing($batch);

        $reader = new Reader;

        try {
            $reader->open($filePath);

            $headerValidated = false;
            $dataRowsFound = false;

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $rowNumber => $row) {
                    $values = $this->normalizeRowWidth($row->toArray());

                    if (! $headerValidated) {
                        $this->assertHeaders($values);
                        $headerValidated = true;

                        continue;
                    }

                    if ($this->isBlankRow($values)) {
                        continue;
                    }

                    $dataRowsFound = true;
                    $payload = array_combine(self::EXPECTED_HEADERS, $values);

                    try {
                        DB::transaction(function () use ($batch, $rowNumber, $payload): void {
                            $part = $this->importRow($payload);
                            $this->batches->recordSuccess($batch, $rowNumber, $payload, $part);
                        });
                    } catch (Throwable $exception) {
                        $this->batches->recordFailure($batch, $rowNumber, $payload, $exception->getMessage());
                    }
                }

                break;
            }

            if (! $headerValidated) {
                throw new InvalidArgumentException('匯入檔案沒有表頭列。');
            }

            if (! $dataRowsFound) {
                throw new InvalidArgumentException('匯入檔案沒有資料列。');
            }
        } catch (Throwable $exception) {
            return $this->batches->markFailed($batch, $exception->getMessage());
        } finally {
            $reader->close();
        }

        return $this->batches->markFinished($batch);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function importRow(array $payload): Part
    {
        $partNumber = $this->stringValue($payload['品號']);
        $name = $this->stringValue($payload['品名']);

        if ($partNumber === null) {
            throw new InvalidArgumentException('品號不可空白。');
        }

        if ($name === null) {
            throw new InvalidArgumentException('品名不可空白。');
        }

        $warehouse = $this->warehouses->upsertWarehouse(
            $this->stringValue($payload['主要倉庫']),
            $this->stringValue($payload['庫別名稱']),
        );

        $supplier = $this->suppliers->upsertSupplier(
            $this->stringValue($payload['主供應商']),
            $this->stringValue($payload['廠商簡稱']),
        );

        $location = $this->warehouses->upsertLocation(
            $warehouse,
            $this->stringValue($payload['儲位']),
        );

        return $this->parts->upsertByPartNumber($partNumber, [
            'name' => $name,
            'barcode' => $this->stringValue($payload['條碼編號']),
            'stock_unit' => $this->stringValue($payload['庫存單位']),
            'product_category' => $this->stringValue($payload['商品分類']),
            'accounting_category' => $this->stringValue($payload['會計分類']),
            'is_stock_calculated' => $this->booleanValue($payload['計算庫存'], '計算庫存'),
            'primary_warehouse_id' => $warehouse?->id,
            'source_code' => $this->stringValue($payload['主要來源']),
            'primary_supplier_id' => $supplier?->id,
            'cycle_count_code' => $this->stringValue($payload['循環盤點碼']),
            'primary_location_id' => $location?->id,
            'is_disabled' => $this->booleanValue($payload['失效'], '失效'),
            'is_replenished_on_demand' => $this->booleanValue($payload['依需求補貨'], '依需求補貨'),
            'lead_days' => $this->integerValue($payload['前置天數'], '前置天數'),
            'safety_stock' => $this->decimalValue($payload['安全存量'], '安全存量'),
            'minimum_replenishment_quantity' => $this->decimalValue($payload['最低補量'], '最低補量'),
            'replenishment_multiple' => $this->decimalValue($payload['補貨倍量'], '補貨倍量'),
            'standard_purchase_price' => $this->decimalValue($payload['標準進價'], '標準進價'),
            'recent_purchase_price' => $this->decimalValue($payload['最近進價'], '最近進價'),
            'retail_price' => $this->decimalValue($payload['零售價'], '零售價'),
            'price_one' => $this->decimalValue($payload['定價一'], '定價一'),
            'price_two' => $this->decimalValue($payload['定價二'], '定價二'),
            'price_three' => $this->decimalValue($payload['定價三'], '定價三'),
            'price_four' => $this->decimalValue($payload['定價四'], '定價四'),
            'low_level_code' => $this->stringValue($payload['低階碼']),
            'description' => $this->stringValue($payload['商品描述']),
            'english_name' => $this->stringValue($payload['英文品名']),
            'english_description' => $this->stringValue($payload['英文描述']),
            'import_tariff_rate' => $this->decimalValue($payload['進口關稅率'], '進口關稅率'),
            'unit_net_weight' => $this->decimalValue($payload['單位淨重'], '單位淨重'),
        ]);
    }

    /**
     * @param  array<int, mixed>  $headers
     */
    private function assertHeaders(array $headers): void
    {
        if ($headers !== self::EXPECTED_HEADERS) {
            throw new InvalidArgumentException('匯入表頭不符合 Warehouse 子品項主檔格式。');
        }
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, mixed>
     */
    private function normalizeRowWidth(array $values): array
    {
        $values = array_slice($values, 0, count(self::EXPECTED_HEADERS));

        return array_pad($values, count(self::EXPECTED_HEADERS), null);
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function isBlankRow(array $values): bool
    {
        foreach ($values as $value) {
            if ($this->stringValue($value) !== null) {
                return false;
            }
        }

        return true;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function booleanValue(mixed $value, string $field): bool
    {
        $value = $this->stringValue($value);

        return match ($value) {
            'T' => true,
            'F', null => false,
            default => throw new InvalidArgumentException("{$field} 必須為 T 或 F。"),
        };
    }

    private function integerValue(mixed $value, string $field): ?int
    {
        if ($this->stringValue($value) === null) {
            return null;
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("{$field} 必須為數字。");
        }

        return (int) $value;
    }

    private function decimalValue(mixed $value, string $field): ?string
    {
        if ($this->stringValue($value) === null) {
            return null;
        }

        if (! is_numeric($value)) {
            throw new InvalidArgumentException("{$field} 必須為數字。");
        }

        return (string) $value;
    }
}
