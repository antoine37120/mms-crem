<?php

namespace App\Policies;

use App\Models\Fond;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FondPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }
    public function view(User $user, Fond $item): bool
    {
        return true;
    }
    public function create(User $user): bool
    {
        return true;
    }
    public function update(User $user, Fond $item): bool
    {
        if ($user->isSuperAdmin()) return true;
        if ($user->hasRole(\App\Enums\UserRole::CHERCHEUR)) return $item->created_by === $user->id;
        if ($user->hasRole(\App\Enums\UserRole::DOCUMENTALISTE)) return $user->hasAccessToModel($item);
        return false;
    }
    public function delete(User $user, Fond $item): bool
    {
        return $this->update($user, $item);
    }
    public function restore(User $user, Fond $item): bool
    {
        return $user->isSuperAdmin();
    }
    public function forceDelete(User $user, Fond $item): bool
    {
        return $user->isSuperAdmin();
    }
    public function audit(User $user, Fond $item): bool
    {
        return true;
    }
    public function restoreAudit(User $user, Fond $item): bool
    {
        return false;
    }
}
