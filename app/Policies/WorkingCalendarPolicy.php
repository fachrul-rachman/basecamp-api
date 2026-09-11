<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkingCalendar;

class WorkingCalendarPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }

    public function view(User $actor, WorkingCalendar $calendar): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }

    public function update(User $actor, WorkingCalendar $calendar): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }

    public function delete(User $actor, WorkingCalendar $calendar): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }

    public function manageExceptions(User $actor, WorkingCalendar $calendar): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }
}
