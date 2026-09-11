<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Thin wrapper around Laravel's native Notifiable dispatch, centralizing
 * the "all Managers of a department" / "all ISO" lookups shared by
 * several notification triggers (docs/02-BUSINESS-RULES.md §19).
 */
class NotificationDispatcher
{
    public function notifyUser(?User $user, Notification $notification): void
    {
        $user?->notify($notification);
    }

    public function notifyManagersOfDepartment(string $departmentId, Notification $notification): void
    {
        Department::with('users.roles')->find($departmentId)?->users
            ->filter(fn (User $user) => $user->hasRole(Role::MANAGER))
            ->each(fn (User $user) => $user->notify($notification));
    }

    public function notifyIso(Notification $notification): void
    {
        User::query()
            ->whereHas('roles', fn ($q) => $q->where('code', Role::ISO))
            ->get()
            ->each(fn (User $user) => $user->notify($notification));
    }
}
