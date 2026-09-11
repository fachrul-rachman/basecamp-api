<?php

namespace App\Policies;

use App\Models\Finding;
use App\Models\Role;
use App\Models\User;

class FindingPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    /**
     * Manual ISO audit findings only (docs/07-AUTHORIZATION.md §5).
     */
    public function create(User $actor): bool
    {
        return $actor->hasRole(Role::ISO);
    }

    public function view(User $actor, Finding $finding): bool
    {
        if ($actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO])) {
            return true;
        }

        if ($finding->target_user_id !== null && $finding->target_user_id === $actor->id) {
            return true;
        }

        if ($actor->hasRole(Role::MANAGER)) {
            return $actor->departments->pluck('id')->contains($finding->target_department_id);
        }

        return false;
    }

    /**
     * Explanation and reopen are both "the responsible department Manager
     * responding to this finding" (docs/02-BUSINESS-RULES.md §6-7).
     */
    public function respond(User $actor, Finding $finding): bool
    {
        return $actor->hasRole(Role::MANAGER)
            && $actor->departments->pluck('id')->contains($finding->target_department_id);
    }

    public function review(User $actor, Finding $finding): bool
    {
        return $actor->hasRole(Role::ISO);
    }
}
