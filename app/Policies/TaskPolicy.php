<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function view(User $actor, Task $task): bool
    {
        if ($actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO])) {
            return true;
        }

        $departmentIds = $actor->departments->pluck('id');

        if ($departmentIds->contains($task->owner_department_id)) {
            return true;
        }

        // Visible to a target department involved via a cross-department
        // request (see docs/02-BUSINESS-RULES.md §12).
        return $task->departmentRequests()->whereIn('target_department_id', $departmentIds)->exists();
    }

    /**
     * @param  string  $ownerDepartmentId  the department the Task would be created under
     */
    public function create(User $actor, string $ownerDepartmentId): bool
    {
        return $actor->hasRole(Role::MANAGER)
            && $actor->departments->pluck('id')->contains($ownerDepartmentId);
    }

    public function update(User $actor, Task $task): bool
    {
        return $this->isOwnerManager($actor, $task);
    }

    public function reschedule(User $actor, Task $task): bool
    {
        return $this->isOwnerManager($actor, $task);
    }

    public function cancel(User $actor, Task $task): bool
    {
        return $this->isOwnerManager($actor, $task);
    }

    public function manageChecklists(User $actor, Task $task): bool
    {
        return $this->isOwnerManager($actor, $task);
    }

    private function isOwnerManager(User $actor, Task $task): bool
    {
        return $actor->hasRole(Role::MANAGER)
            && $actor->departments->pluck('id')->contains($task->owner_department_id);
    }
}
