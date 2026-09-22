<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Part extends Model
{
    use HasFactory;

    protected $fillable = [
        'part_number',
        'name',
        'barcode',
        'stock_unit',
        'product_category',
        'accounting_category',
        'is_stock_calculated',
        'primary_warehouse_id',
        'source_code',
        'primary_supplier_id',
        'cycle_count_code',
        'primary_location_id',
        'is_disabled',
        'is_replenished_on_demand',
        'lead_days',
        'safety_stock',
        'minimum_replenishment_quantity',
        'replenishment_multiple',
        'standard_purchase_price',
        'recent_purchase_price',
        'retail_price',
        'price_one',
        'price_two',
        'price_three',
        'price_four',
        'low_level_code',
        'description',
        'english_name',
        'english_description',
        'import_tariff_rate',
        'unit_net_weight',
    ];

    protected function casts(): array
    {
        return [
            'is_stock_calculated' => 'boolean',
            'is_disabled' => 'boolean',
            'is_replenished_on_demand' => 'boolean',
            'lead_days' => 'integer',
            'safety_stock' => 'decimal:6',
            'minimum_replenishment_quantity' => 'decimal:6',
            'replenishment_multiple' => 'decimal:6',
            'standard_purchase_price' => 'decimal:6',
            'recent_purchase_price' => 'decimal:6',
            'retail_price' => 'decimal:6',
            'price_one' => 'decimal:6',
            'price_two' => 'decimal:6',
            'price_three' => 'decimal:6',
            'price_four' => 'decimal:6',
            'import_tariff_rate' => 'decimal:6',
            'unit_net_weight' => 'decimal:6',
        ];
    }

    public function primaryWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'primary_warehouse_id');
    }

    public function primarySupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'primary_supplier_id');
    }

    public function primaryLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'primary_location_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function inventoryDocumentItems(): HasMany
    {
        return $this->hasMany(InventoryDocumentItem::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($term): void {
            $query
                ->where('part_number', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('barcode', 'like', "%{$term}%");
        });
    }
}
