<?php

namespace App\Policies;

use App\Models\Part;
use App\Models\User;

class PartPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Part');
    }

    public function view(User $user, Part $part): bool
    {
        return $user->can('View:Part');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:Part');
    }

    public function update(User $user, Part $part): bool
    {
        return $user->can('Update:Part');
    }

    public function delete(User $user, Part $part): bool
    {
        return $user->can('Delete:Part');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('DeleteAny:Part');
    }
}
