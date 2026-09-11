<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $actor): bool
    {
        // Every authenticated role has some level of read access; the
        // controller scopes the query itself (global vs own-department).
        return true;
    }

    public function view(User $actor, Department $department): bool
    {
        if ($actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO])) {
            return true;
        }

        return $actor->departments->pluck('id')->contains($department->id);
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }

    public function update(User $actor, Department $department): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }

    public function delete(User $actor, Department $department): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }

    public function manageMembers(User $actor, Department $department): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }
}
