<?php

namespace App\Repositories;

use App\Models\Inventory;
use Illuminate\Database\Eloquent\Builder;

class InventoryRepository
{
    public function firstOrCreateSnapshot(int $partId, int $warehouseId, ?int $warehouseLocationId): Inventory
    {
        return Inventory::query()->firstOrCreate(
            [
                'part_id' => $partId,
                'warehouse_id' => $warehouseId,
                'location_key' => Inventory::locationKey($warehouseLocationId),
            ],
            [
                'warehouse_location_id' => $warehouseLocationId,
                'quantity' => 0,
            ],
        );
    }

    public function lockSnapshot(Inventory $inventory): Inventory
    {
        return Inventory::query()
            ->whereKey($inventory->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function queryForLookup(array $filters = []): Builder
    {
        return Inventory::query()
            ->with(['part', 'warehouse', 'warehouseLocation'])
            ->when($filters['search'] ?? null, function (Builder $query, string $term): Builder {
                return $query->whereHas('part', fn (Builder $partQuery): Builder => $partQuery->search($term));
            })
            ->when($filters['part_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('part_id', $id))
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('warehouse_id', $id))
            ->when($filters['location_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('warehouse_location_id', $id))
            ->when(array_key_exists('is_disabled', $filters), fn (Builder $query): Builder => $query->whereHas('part', fn (Builder $partQuery): Builder => $partQuery->where('is_disabled', (bool) $filters['is_disabled'])))
            ->when(array_key_exists('is_stock_calculated', $filters), fn (Builder $query): Builder => $query->whereHas('part', fn (Builder $partQuery): Builder => $partQuery->where('is_stock_calculated', (bool) $filters['is_stock_calculated'])));
    }
}
