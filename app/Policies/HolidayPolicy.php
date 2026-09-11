<?php

namespace App\Policies;

use App\Models\Holiday;
use App\Models\Role;
use App\Models\User;

class HolidayPolicy
{
    public function viewAny(User $actor): bool
    {
        // Every authenticated role needs to know which days are holidays.
        return true;
    }

    public function view(User $actor, Holiday $holiday): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }

    public function update(User $actor, Holiday $holiday): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }

    public function delete(User $actor, Holiday $holiday): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }
}
