<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class UserService
{
    /**
     * Roles only SuperAdmin may grant or revoke (see docs/07-AUTHORIZATION.md §3, §6).
     */
    private const PROTECTED_ROLES = [Role::SUPERADMIN, Role::ADMIN];

    public function __construct(private AuditLogService $auditLog) {}

    public function create(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (array_key_exists('role_codes', $data)) {
                $this->syncRoles($actor, $user, $data['role_codes'] ?? []);
            }

            if (array_key_exists('department_ids', $data)) {
                $this->syncDepartments($user, $data['department_ids'] ?? [], $data['primary_department_id'] ?? null);
            }

            $this->auditLog->record($actor, 'user.created', 'User', $user->id);

            return $user->load(['roles', 'departments']);
        });
    }

    public function update(User $actor, User $user, array $data): User
    {
        return DB::transaction(function () use ($actor, $user, $data) {
            $user->fill(array_intersect_key($data, array_flip(['name', 'email', 'is_active'])))->save();

            if (array_key_exists('role_codes', $data)) {
                $this->syncRoles($actor, $user, $data['role_codes'] ?? []);
            }

            if (array_key_exists('department_ids', $data)) {
                $this->syncDepartments($user, $data['department_ids'] ?? [], $data['primary_department_id'] ?? null);
            }

            $this->auditLog->record($actor, 'user.updated', 'User', $user->id);

            return $user->load(['roles', 'departments']);
        });
    }

    public function deactivate(User $actor, User $user): User
    {
        $user->update(['is_active' => false]);
        $this->auditLog->record($actor, 'user.deactivated', 'User', $user->id);

        return $user;
    }

    /**
     * @param  string[]  $roleCodes
     */
    private function syncRoles(User $actor, User $user, array $roleCodes): void
    {
        $currentProtected = $user->roles->pluck('code')->intersect(self::PROTECTED_ROLES)->sort()->values()->all();
        $requestedProtected = collect($roleCodes)->intersect(self::PROTECTED_ROLES)->sort()->values()->all();

        if (! $actor->hasRole(Role::SUPERADMIN) && $currentProtected !== $requestedProtected) {
            throw new AuthorizationException('Only SuperAdmin may change the Admin or SuperAdmin role assignment.');
        }

        $roleIds = Role::whereIn('code', $roleCodes)->pluck('id');
        $user->roles()->sync($roleIds);
    }

    /**
     * @param  string[]  $departmentIds
     */
    private function syncDepartments(User $user, array $departmentIds, ?string $primaryDepartmentId): void
    {
        $pivotData = collect($departmentIds)->mapWithKeys(fn ($id) => [
            $id => ['is_primary' => $id === $primaryDepartmentId],
        ])->all();

        $user->departments()->sync($pivotData);
    }
}
