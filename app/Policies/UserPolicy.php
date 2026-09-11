<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO, Role::MANAGER]);
    }

    public function view(User $actor, User $user): bool
    {
        if ($actor->is($user)) {
            return true;
        }

        if ($actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO])) {
            return true;
        }

        if ($actor->hasRole(Role::MANAGER)) {
            return $actor->departments->pluck('id')
                ->intersect($user->departments->pluck('id'))
                ->isNotEmpty();
        }

        return false;
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }

    public function update(User $actor, User $user): bool
    {
        // SuperAdmin needs this to reach the protected-role-assignment check
        // in UserService (see docs/07-AUTHORIZATION.md §2, §3).
        return $actor->hasAnyRole([Role::ADMIN, Role::SUPERADMIN]);
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }
}
