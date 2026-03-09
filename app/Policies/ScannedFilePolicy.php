<?php

namespace App\Policies;

use App\Models\ScannedFile;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ScannedFilePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, ScannedFile $scannedFile): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, ScannedFile $scannedFile): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, ScannedFile $scannedFile): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user, ScannedFile $scannedFile): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, ScannedFile $scannedFile): bool
    {
        return $user->isSuperAdmin();
    }
}
