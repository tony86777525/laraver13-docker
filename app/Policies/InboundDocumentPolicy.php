<?php

namespace App\Policies;

use App\Models\InboundDocument;
use App\Models\User;

class InboundDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:InboundDocument');
    }

    public function view(User $user, InboundDocument $document): bool
    {
        return $user->can('View:InboundDocument');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:InboundDocument');
    }

    public function update(User $user, InboundDocument $document): bool
    {
        return $user->can('Update:InboundDocument');
    }

    public function delete(User $user, InboundDocument $document): bool
    {
        return false;
    }
}
