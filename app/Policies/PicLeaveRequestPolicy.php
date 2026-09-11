<?php

namespace App\Policies;

use App\Models\PicLeaveRequest;
use App\Models\Role;
use App\Models\User;

class PicLeaveRequestPolicy
{
    public function viewAny(User $actor): bool
    {
        return true;
    }

    public function create(User $actor): bool
    {
        return $actor->hasRole(Role::MANAGER);
    }

    public function view(User $actor, PicLeaveRequest $leaveRequest): bool
    {
        if ($actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO])) {
            return true;
        }

        if ($leaveRequest->pic_id === $actor->id) {
            return true;
        }

        if ($actor->hasRole(Role::MANAGER)) {
            $picDepartmentIds = $leaveRequest->pic->departments->pluck('id');

            return $actor->departments->pluck('id')->intersect($picDepartmentIds)->isNotEmpty();
        }

        return false;
    }

    public function review(User $actor, PicLeaveRequest $leaveRequest): bool
    {
        return $actor->hasRole(Role::ISO);
    }
}
