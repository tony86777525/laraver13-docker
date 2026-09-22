<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasFactory;

    public const NO_LOCATION_KEY = '__no_location__';

    protected $fillable = [
        'part_id',
        'warehouse_id',
        'warehouse_location_id',
        'location_key',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
        ];
    }

    public static function locationKey(?int $warehouseLocationId): string
    {
        return $warehouseLocationId === null ? self::NO_LOCATION_KEY : (string) $warehouseLocationId;
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }
}
