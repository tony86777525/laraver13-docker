<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class InboundDocument extends InventoryDocument
{
    protected $table = 'inventory_documents';

    protected static function booted(): void
    {
        static::addGlobalScope(
            'inbound',
            fn (Builder $query): Builder => $query->where('direction', self::DIRECTION_INBOUND),
        );
    }
}
