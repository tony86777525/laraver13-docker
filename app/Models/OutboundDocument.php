<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class OutboundDocument extends InventoryDocument
{
    protected $table = 'inventory_documents';

    protected static function booted(): void
    {
        static::addGlobalScope(
            'outbound',
            fn (Builder $query): Builder => $query->where('direction', self::DIRECTION_OUTBOUND),
        );
    }
}
