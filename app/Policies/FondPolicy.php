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
        return true;
    }

    public function delete(User $user, Fond $item): bool
    {
        return true;
    }

    public function restore(User $user, Fond $item): bool
    {
        return true;
    }

    public function forceDelete(User $user, Fond $item): bool
    {
        return true;
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
