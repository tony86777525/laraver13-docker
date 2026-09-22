<?php

namespace App\Policies;

use App\Models\InventoryTransaction;
use App\Models\User;

class InventoryTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:InventoryTransaction');
    }

    public function view(User $user, InventoryTransaction $inventoryTransaction): bool
    {
        return $user->can('View:InventoryTransaction');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, InventoryTransaction $inventoryTransaction): bool
    {
        return false;
    }

    public function delete(User $user, InventoryTransaction $inventoryTransaction): bool
    {
        return false;
    }
}
