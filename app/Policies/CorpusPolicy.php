<?php

namespace App\Policies;

use App\Models\Corpus;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CorpusPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Corpus $item): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Corpus $item): bool
    {
        return true;
    }

    public function delete(User $user, Corpus $item): bool
    {
        return true;
    }

    public function restore(User $user, Corpus $item): bool
    {
        return true;
    }

    public function forceDelete(User $user, Corpus $item): bool
    {
        return true;
    }
    public function audit(User $user, Corpus $item): bool
    {
        return true;
    }
    public function restoreAudit(User $user, Corpus $item): bool
    {
        return false;
    }



}
