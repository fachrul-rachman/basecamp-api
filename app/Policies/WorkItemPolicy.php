<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkItem;

class WorkItemPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, WorkItem $workItem): bool
    {
        if ($actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO])) {
            return true;
        }

        if ($workItem->assignee_id === $actor->id) {
            return true;
        }

        if ($actor->hasRole(Role::MANAGER)) {
            $departmentIds = $actor->departments->pluck('id');

            return $departmentIds->contains($workItem->responsible_department_id)
                || $departmentIds->contains($workItem->task->owner_department_id);
        }

        return false;
    }

    public function submit(User $actor, WorkItem $workItem): bool
    {
        return $workItem->assignee_id !== null && $workItem->assignee_id === $actor->id;
    }

    public function reassign(User $actor, WorkItem $workItem): bool
    {
        return $actor->hasRole(Role::MANAGER)
            && $actor->departments->pluck('id')->contains($workItem->responsible_department_id);
    }

    public function reviewEvidence(User $actor, WorkItem $workItem): bool
    {
        return $actor->hasRole(Role::MANAGER)
            && $actor->departments->pluck('id')->contains($workItem->responsible_department_id);
    }
}
