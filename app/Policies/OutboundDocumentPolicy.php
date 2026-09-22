<?php

namespace App\Policies;

use App\Models\OutboundDocument;
use App\Models\User;

class OutboundDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:OutboundDocument');
    }

    public function view(User $user, OutboundDocument $document): bool
    {
        return $user->can('View:OutboundDocument');
    }

    public function create(User $user): bool
    {
        return $user->can('Create:OutboundDocument');
    }

    public function update(User $user, OutboundDocument $document): bool
    {
        return $user->can('Update:OutboundDocument');
    }

    public function delete(User $user, OutboundDocument $document): bool
    {
        return false;
    }
}
