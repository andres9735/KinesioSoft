<?php

namespace App\Policies;

use App\Models\AntecedenteFamiliar;
use App\Models\User;

class AntecedenteFamiliarPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasAnyRole(['Kinesiologa'])) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return false;
    }
    public function view(User $user, AntecedenteFamiliar $model): bool
    {
        return false;
    }
    public function create(User $user): bool
    {
        return false;
    }
    public function update(User $user, AntecedenteFamiliar $model): bool
    {
        return false;
    }
    public function delete(User $user, AntecedenteFamiliar $model): bool
    {
        return false;
    }
    public function deleteAny(User $user): bool
    {
        return false;
    }
}
