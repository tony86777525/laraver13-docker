<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDocumentRevision extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'revision',
        'snapshot',
        'operator_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class, 'inventory_document_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
