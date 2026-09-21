<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Supplier');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can('View:Supplier');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Supplier');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can('Update:Supplier');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can('Delete:Supplier');
    }
}
