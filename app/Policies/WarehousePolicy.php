<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Warehouse');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->can('View:Warehouse');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Warehouse');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->can('Update:Warehouse');
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->can('Delete:Warehouse') && ! $warehouse->inventoryDocuments()->exists();
    }
}
