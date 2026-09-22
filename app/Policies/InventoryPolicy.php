<?php

namespace App\Policies;

use App\Models\Inventory;
use App\Models\User;

class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Inventory');
    }

    public function view(User $user, Inventory $inventory): bool
    {
        return $user->can('View:Inventory');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Inventory');
    }

    public function update(User $user, Inventory $inventory): bool
    {
        return $user->can('Update:Inventory');
    }

    public function delete(User $user, Inventory $inventory): bool
    {
        return false;
    }
}
