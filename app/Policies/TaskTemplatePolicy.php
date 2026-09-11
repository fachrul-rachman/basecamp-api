<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\TaskTemplate;
use App\Models\User;

class TaskTemplatePolicy
{
    public function viewAny(User $actor): bool
    {
        // Every role has some read access (Resource Matrix: "R/use only"
        // for Manager, "R available" for PIC); the controller scopes the
        // query to available-for-department templates for those roles.
        return true;
    }

    public function view(User $actor, TaskTemplate $template): bool
    {
        if ($actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO])) {
            return true;
        }

        if (! $template->is_active) {
            return false;
        }

        $departmentIds = $template->departments->pluck('id');

        // No department rows means the template is available company-wide
        // (see docs/03-DOMAIN-MODEL.md §4).
        if ($departmentIds->isEmpty()) {
            return true;
        }

        return $departmentIds->intersect($actor->departments->pluck('id'))->isNotEmpty();
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }

    public function update(User $actor, TaskTemplate $template): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }

    public function delete(User $actor, TaskTemplate $template): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }

    public function manageReferenceEvidence(User $actor, TaskTemplate $template): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }
}
