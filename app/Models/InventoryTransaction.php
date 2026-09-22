<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    use HasFactory;

    public const TYPE_INBOUND = 'inbound';

    public const TYPE_OUTBOUND = 'outbound';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_CYCLE_COUNT = 'cycle_count';

    public const TYPE_IMPORT_CORRECTION = 'import_correction';

    protected $fillable = [
        'inventory_id',
        'inventory_document_id',
        'document_revision',
        'part_id',
        'warehouse_id',
        'warehouse_location_id',
        'transaction_type',
        'before_quantity',
        'quantity_delta',
        'after_quantity',
        'reference_type',
        'reference_number',
        'operator_id',
        'occurred_at',
        'memo',
    ];

    protected function casts(): array
    {
        return [
            'before_quantity' => 'decimal:6',
            'quantity_delta' => 'decimal:6',
            'after_quantity' => 'decimal:6',
            'document_revision' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            self::TYPE_INBOUND => 'Inbound',
            self::TYPE_OUTBOUND => 'Outbound',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            self::TYPE_CYCLE_COUNT => 'Cycle count',
            self::TYPE_IMPORT_CORRECTION => 'Import correction',
        ];
    }

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    public function inventoryDocument(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class);
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

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
