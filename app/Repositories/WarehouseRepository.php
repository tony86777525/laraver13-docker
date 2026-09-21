<?php

namespace App\Repositories;

use App\Models\Warehouse;
use App\Models\WarehouseLocation;

class WarehouseRepository
{
    public function upsertWarehouse(?string $code, ?string $name): ?Warehouse
    {
        if (blank($code)) {
            return null;
        }

        return Warehouse::query()->updateOrCreate(
            ['code' => $code],
            ['name' => $name],
        );
    }

    public function upsertLocation(?Warehouse $warehouse, ?string $code): ?WarehouseLocation
    {
        if (! $warehouse || blank($code)) {
            return null;
        }

        return WarehouseLocation::query()->updateOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'code' => $code,
            ],
            [],
        );
    }
}
