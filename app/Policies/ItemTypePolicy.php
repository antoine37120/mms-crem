<?php

namespace App\Policies;

use App\Models\ItemType;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ItemTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ItemType $item): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ItemType $item): bool
    {
        return true;
    }

    public function delete(User $user, ItemType $item): bool
    {
        return true;
    }

    public function restore(User $user, ItemType $item): bool
    {
        return true;
    }

    public function forceDelete(User $user, ItemType $item): bool
    {
        return true;
    }
    public function audit(User $user, ItemType $item): bool
    {
        return true;
    }
    public function restoreAudit(User $user, ItemType $item): bool
    {
        return false;
    }



}
