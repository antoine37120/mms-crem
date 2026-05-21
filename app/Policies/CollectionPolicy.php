<?php

namespace App\Policies;

use App\Models\Collection;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CollectionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Collection $item): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Collection $item): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }
        if ($user->hasRole(\App\Enums\UserRole::CHERCHEUR)) {
            return $item->created_by === $user->id;
        }
        if ($user->hasRole(\App\Enums\UserRole::DOCUMENTALISTE)) {
            return $user->hasAccessToModel($item);
        }

        return false;
    }

    public function delete(User $user, Collection $item): bool
    {
        return $this->update($user, $item);
    }

    public function restore(User $user, Collection $item): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, Collection $item): bool
    {
        return $user->isSuperAdmin();
    }

    public function audit(User $user, Collection $item): bool
    {
        return true;
    }

    public function restoreAudit(User $user, Collection $item): bool
    {
        return false;
    }
}
