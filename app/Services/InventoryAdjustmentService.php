<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\User;
use App\Models\WarehouseLocation;
use App\Repositories\InventoryRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentService
{
    public function __construct(
        private readonly InventoryRepository $inventories,
    ) {}

    /**
     * @param  array{
     *     part_id:int,
     *     warehouse_id:int,
     *     warehouse_location_id?:int|null,
     *     quantity_delta:string|int|float,
     *     transaction_type:string,
     *     reference_type?:string|null,
     *     reference_number?:string|null,
     *     memo?:string|null,
     *     occurred_at?:Carbon|string|null
     * }  $data
     */
    public function adjust(array $data, ?User $operator = null): InventoryTransaction
    {
        if (! in_array($data['transaction_type'], [
            InventoryTransaction::TYPE_ADJUSTMENT,
            InventoryTransaction::TYPE_CYCLE_COUNT,
            InventoryTransaction::TYPE_IMPORT_CORRECTION,
        ], true)) {
            throw ValidationException::withMessages([
                'transaction_type' => '進庫與出庫必須使用正式單據。',
            ]);
        }

        $warehouseLocationId = $data['warehouse_location_id'] ?? null;
        $this->assertLocationBelongsToWarehouse($warehouseLocationId, $data['warehouse_id']);

        return DB::transaction(function () use ($data, $operator, $warehouseLocationId): InventoryTransaction {
            $inventory = $this->inventories->firstOrCreateSnapshot(
                $data['part_id'],
                $data['warehouse_id'],
                $warehouseLocationId,
            );
            $inventory = $this->inventories->lockSnapshot($inventory);

            $beforeQuantity = $this->normalizeDecimal($inventory->quantity);
            $quantityDelta = $this->normalizeDecimal($data['quantity_delta']);
            $afterQuantity = $this->addDecimal($beforeQuantity, $quantityDelta);

            if (! config('warehouse.allow_negative_inventory') && bccomp($afterQuantity, '0.000000', 6) < 0) {
                throw ValidationException::withMessages([
                    'quantity_delta' => '庫存不足，調整後數量不可小於 0。',
                ]);
            }

            $transaction = InventoryTransaction::query()->create([
                'inventory_id' => $inventory->id,
                'part_id' => $inventory->part_id,
                'warehouse_id' => $inventory->warehouse_id,
                'warehouse_location_id' => $inventory->warehouse_location_id,
                'transaction_type' => $data['transaction_type'],
                'before_quantity' => $beforeQuantity,
                'quantity_delta' => $quantityDelta,
                'after_quantity' => $afterQuantity,
                'reference_type' => $this->nullableString($data['reference_type'] ?? null),
                'reference_number' => $this->nullableString($data['reference_number'] ?? null),
                'operator_id' => $operator?->id,
                'occurred_at' => $this->occurredAt($data['occurred_at'] ?? null),
                'memo' => $this->nullableString($data['memo'] ?? null),
            ]);

            $inventory->forceFill([
                'quantity' => $afterQuantity,
            ])->save();

            return $transaction;
        });
    }

    private function assertLocationBelongsToWarehouse(?int $warehouseLocationId, int $warehouseId): void
    {
        if ($warehouseLocationId === null) {
            return;
        }

        $exists = WarehouseLocation::query()
            ->whereKey($warehouseLocationId)
            ->where('warehouse_id', $warehouseId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'warehouse_location_id' => '儲位必須屬於選定倉庫。',
            ]);
        }
    }

    private function occurredAt(Carbon|string|null $occurredAt): Carbon
    {
        if ($occurredAt instanceof Carbon) {
            return $occurredAt;
        }

        if (filled($occurredAt)) {
            return Carbon::parse($occurredAt);
        }

        return Carbon::now();
    }

    private function addDecimal(string $left, string $right): string
    {
        return bcadd($left, $right, 6);
    }

    private function normalizeDecimal(mixed $value): string
    {
        return bcadd((string) $value, '0', 6);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
