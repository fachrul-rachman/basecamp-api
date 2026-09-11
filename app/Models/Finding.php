<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'source_type',
    'finding_type',
    'task_id',
    'task_checklist_id',
    'work_item_id',
    'source_evidence_id',
    'target_department_id',
    'target_user_id',
    'created_by',
    'title',
    'description',
    'status',
    'resolution_type',
    'due_at',
    'is_self_handled',
    'opened_at',
    'resolved_at',
])]
class Finding extends Model
{
    use HasUuids;

    public const SOURCE_AUTOMATIC = 'automatic';

    public const SOURCE_ISO_MANUAL = 'iso_manual';

    public const SOURCE_SLA = 'sla';

    public const TYPE_LATE = 'late';

    public const TYPE_FAILED = 'failed';

    public const TYPE_MANAGER_SLA_BREACH = 'manager_sla_breach';

    public const TYPE_ISO_MANUAL = 'iso_manual';

    // Automatic-finding lifecycle (Phase 7).
    public const STATUS_OPEN = 'open';

    public const STATUS_WAITING_MANAGER_ACTION = 'waiting_manager_action';

    public const STATUS_WAITING_ISO_REVIEW = 'waiting_iso_review';

    public const STATUS_REOPENED = 'reopened';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_ESCALATED = 'escalated';

    // ISO manual finding lifecycle (Phase 8) — deliberately distinct from
    // the automatic lifecycle above; a manual finding never carries
    // waiting_manager_action/waiting_iso_review/reopened/escalated, and an
    // automatic finding never carries closed/info (docs/02-BUSINESS-RULES.md
    // §17, Phase 8 acceptance: "manual finding remains distinct from
    // failed checklist semantics"). STATUS_OPEN is shared by both.
    public const STATUS_CLOSED = 'closed';

    public const STATUS_INFO = 'info';

    protected function casts(): array
    {
        return [
            'is_self_handled' => 'boolean',
            'due_at' => 'datetime',
            'opened_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<TaskChecklist, $this>
     */
    public function taskChecklist(): BelongsTo
    {
        return $this->belongsTo(TaskChecklist::class);
    }

    /**
     * @return BelongsTo<WorkItem, $this>
     */
    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function targetDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * @return HasMany<FindingAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(FindingAction::class)->orderBy('created_at');
    }

    /**
     * @return HasMany<SlaInstance, $this>
     */
    public function slaInstances(): HasMany
    {
        return $this->hasMany(SlaInstance::class);
    }

    /**
     * @return HasMany<WorkReopen, $this>
     */
    public function reopens(): HasMany
    {
        return $this->hasMany(WorkReopen::class);
    }

    /**
     * @return HasMany<FindingEvidence, $this>
     */
    public function evidence(): HasMany
    {
        return $this->hasMany(FindingEvidence::class);
    }
}
