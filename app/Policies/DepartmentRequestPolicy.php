<?php

namespace App\Policies;

use App\Models\DepartmentRequest;
use App\Models\Role;
use App\Models\User;

class DepartmentRequestPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, DepartmentRequest $departmentRequest): bool
    {
        if ($actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO])) {
            return true;
        }

        $departmentIds = $actor->departments->pluck('id');

        return $departmentIds->contains($departmentRequest->owner_department_id)
            || $departmentIds->contains($departmentRequest->target_department_id);
    }

    /**
     * Assign/reject/reassign are all "the target department Manager
     * responding to this request" (see docs/02-BUSINESS-RULES.md §12).
     */
    public function respond(User $actor, DepartmentRequest $departmentRequest): bool
    {
        return $actor->hasRole(Role::MANAGER)
            && $actor->departments->pluck('id')->contains($departmentRequest->target_department_id);
    }
}
