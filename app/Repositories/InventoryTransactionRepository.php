<?php

namespace App\Repositories;

use App\Models\InventoryTransaction;
use Illuminate\Database\Eloquent\Builder;

class InventoryTransactionRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function queryForHistory(array $filters = []): Builder
    {
        return InventoryTransaction::query()
            ->with(['part', 'warehouse', 'warehouseLocation', 'operator'])
            ->when($filters['part_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('part_id', $id))
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('warehouse_id', $id))
            ->when($filters['location_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('warehouse_location_id', $id))
            ->when($filters['transaction_type'] ?? null, fn (Builder $query, string $type): Builder => $query->where('transaction_type', $type))
            ->when($filters['reference'] ?? null, function (Builder $query, string $reference): Builder {
                return $query->where(function (Builder $query) use ($reference): void {
                    $query
                        ->where('reference_type', 'like', "%{$reference}%")
                        ->orWhere('reference_number', 'like', "%{$reference}%");
                });
            })
            ->when($filters['occurred_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('occurred_at', '>=', $date))
            ->when($filters['occurred_until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('occurred_at', '<=', $date));
    }
}
