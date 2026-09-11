<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * See docs/07-AUTHORIZATION.md §9: Global/Department/Manager SLA is
 * managed exclusively by ISO; ISO SLA is managed exclusively by Admin.
 * SuperAdmin/Admin/ISO may read all scopes; Manager/Director/PIC do not
 * use this raw config surface (Manager reads only effective SLA via a
 * Finding, Director via reporting — neither exists yet in Phase 2).
 */
class SlaSettingPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasAnyRole([Role::SUPERADMIN, Role::ADMIN, Role::ISO]);
    }

    public function manageManagerScope(User $actor): bool
    {
        return $actor->hasRole(Role::ISO);
    }

    public function manageIsoScope(User $actor): bool
    {
        return $actor->hasRole(Role::ADMIN);
    }
}
