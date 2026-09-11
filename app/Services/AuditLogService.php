<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogService
{
    /**
     * Record a meaningful operational state change (see docs/05-DATABASE-SCHEMA.md §30).
     * Not for every read; only for actions worth an audit trail.
     */
    public function record(?User $actor, string $action, string $entityType, ?string $entityId, array $metadata = []): AuditLog
    {
        return AuditLog::create([
            'actor_id' => $actor?->id,
            'actor_role' => $actor ? $actor->roles->pluck('code')->implode(',') : null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
        ]);
    }
}
