<?php

namespace App\Services;

use App\Models\SlaSetting;
use App\Models\User;
use RuntimeException;

/**
 * Resolves and manages effective SLA minutes (see docs/02-BUSINESS-RULES.md
 * §8-9, docs/05-DATABASE-SCHEMA.md §10). Manager SLA precedence is
 * manager > department > global, all configured by ISO. ISO SLA is a
 * separate single global value configured by Admin only.
 */
class SlaSettingService
{
    public function resolveManagerSla(User $manager): int
    {
        $managerOverride = $this->activeMinutes(SlaSetting::SCOPE_MANAGER, $manager->id);

        if ($managerOverride !== null) {
            return $managerOverride;
        }

        $departmentId = $manager->primaryDepartment()?->id;

        if ($departmentId) {
            $departmentOverride = $this->activeMinutes(SlaSetting::SCOPE_DEPARTMENT, $departmentId);

            if ($departmentOverride !== null) {
                return $departmentOverride;
            }
        }

        $global = $this->activeMinutes(SlaSetting::SCOPE_GLOBAL, null);

        if ($global === null) {
            throw new RuntimeException('No global Manager SLA is configured.');
        }

        return $global;
    }

    public function resolveIsoSla(): int
    {
        $minutes = $this->activeMinutes(SlaSetting::SCOPE_ISO, null);

        if ($minutes === null) {
            throw new RuntimeException('No ISO SLA is configured.');
        }

        return $minutes;
    }

    /**
     * Set or clear an SLA scope. Passing null minutes deactivates the
     * existing row for that scope (see docs/06-API-CONTRACT.md §11,
     * "sets/removes").
     */
    public function set(string $scopeType, ?string $scopeId, ?int $minutes): ?SlaSetting
    {
        if ($minutes === null) {
            SlaSetting::query()
                ->where('scope_type', $scopeType)
                ->where('scope_id', $scopeId)
                ->update(['is_active' => false]);

            return null;
        }

        return SlaSetting::query()->updateOrCreate(
            ['scope_type' => $scopeType, 'scope_id' => $scopeId],
            ['minutes' => $minutes, 'is_active' => true]
        );
    }

    private function activeMinutes(string $scopeType, ?string $scopeId): ?int
    {
        return SlaSetting::query()
            ->where('scope_type', $scopeType)
            ->where('scope_id', $scopeId)
            ->where('is_active', true)
            ->value('minutes');
    }
}
