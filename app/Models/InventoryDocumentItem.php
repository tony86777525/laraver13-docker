<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDocumentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'part_id',
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

    public function document(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class, 'inventory_document_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function warehouseLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class);
    }
}
