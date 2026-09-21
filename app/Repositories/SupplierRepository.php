<?php

namespace App\Repositories;

use App\Models\Supplier;

class SupplierRepository
{
    public function upsertSupplier(?string $code, ?string $shortName): ?Supplier
    {
        if (blank($code)) {
            return null;
        }

        return Supplier::query()->updateOrCreate(
            ['code' => $code],
            ['short_name' => $shortName],
        );
    }
}
