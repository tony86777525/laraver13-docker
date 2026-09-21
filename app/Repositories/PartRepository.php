<?php

namespace App\Repositories;

use App\Models\Part;
use Illuminate\Database\Eloquent\Builder;

class PartRepository
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertByPartNumber(string $partNumber, array $attributes): Part
    {
        return Part::query()->updateOrCreate(
            ['part_number' => $partNumber],
            $attributes,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function queryForMasterSearch(array $filters = []): Builder
    {
        return Part::query()
            ->with(['primaryWarehouse', 'primarySupplier', 'primaryLocation'])
            ->search($filters['search'] ?? null)
            ->when($filters['product_category'] ?? null, fn (Builder $query, string $value): Builder => $query->where('product_category', $value))
            ->when($filters['accounting_category'] ?? null, fn (Builder $query, string $value): Builder => $query->where('accounting_category', $value))
            ->when($filters['supplier_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('primary_supplier_id', $id))
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('primary_warehouse_id', $id))
            ->when($filters['location_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('primary_location_id', $id))
            ->when(array_key_exists('is_disabled', $filters), fn (Builder $query): Builder => $query->where('is_disabled', (bool) $filters['is_disabled']))
            ->when(array_key_exists('is_stock_calculated', $filters), fn (Builder $query): Builder => $query->where('is_stock_calculated', (bool) $filters['is_stock_calculated']));
    }
}
