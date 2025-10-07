<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $item): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, User $item): bool
    {
        return true;
    }

    public function delete(User $user, User $item): bool
    {
        return true;
    }

    public function restore(User $user, User $item): bool
    {
        return true;
    }

    public function forceDelete(User $user, User $item): bool
    {
        return true;
    }
    public function audit(User $user, User $item): bool
    {
        return true;
    }
    public function restoreAudit(User $user, User $item): bool
    {
        return false;
    }



}
