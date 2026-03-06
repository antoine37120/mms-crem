<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, User $item): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, User $item): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, User $item): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user, User $item): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, User $item): bool
    {
        return $user->isSuperAdmin();
    }
    public function audit(User $user, User $item): bool
    {
        return clone $user->isSuperAdmin();
    }
    public function restoreAudit(User $user, User $item): bool
    {
        return false;
    }
}
