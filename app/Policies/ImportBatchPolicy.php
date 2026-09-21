<?php

namespace App\Policies;

use App\Models\ImportBatch;
use App\Models\User;

class ImportBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:ImportBatch');
    }

    public function view(User $user, ImportBatch $importBatch): bool
    {
        return $user->can('View:ImportBatch');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, ImportBatch $importBatch): bool
    {
        return false;
    }

    public function delete(User $user, ImportBatch $importBatch): bool
    {
        return false;
    }
}
