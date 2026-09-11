<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'task_id',
    'task_checklist_id',
    'department_request_id',
    'responsible_department_id',
    'assignee_id',
    'operational_date',
    'period_start',
    'period_end',
    'available_at',
    'deadline_at',
    'failure_at',
    'execution_status',
    'compliance_status',
    'required_evidence_count',
    'rule_snapshot',
    'submitted_at',
    'completed_at',
    'locked_at',
    'reopen_count',
])]
class WorkItem extends Model
{
    use HasUuids;

    public const EXECUTION_PENDING = 'pending';

    public const EXECUTION_IN_PROGRESS = 'in_progress';

    public const EXECUTION_SUBMITTED = 'submitted';

    public const EXECUTION_COMPLETED = 'completed';

    public const EXECUTION_FAILED = 'failed';

    public const EXECUTION_CANCELLED = 'cancelled';

    public const EXECUTION_RESCHEDULED = 'rescheduled';

    public const COMPLIANCE_PENDING = 'pending';

    public const COMPLIANCE_ON_TIME = 'on_time';

    public const COMPLIANCE_LATE = 'late';

    public const COMPLIANCE_FAILED = 'failed';

    public const COMPLIANCE_NOT_APPLICABLE = 'not_applicable';

    protected function casts(): array
    {
        return [
            'operational_date' => 'date',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'available_at' => 'datetime',
            'deadline_at' => 'datetime',
            'failure_at' => 'datetime',
            'required_evidence_count' => 'integer',
            'rule_snapshot' => 'array',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'locked_at' => 'datetime',
            'reopen_count' => 'integer',
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
     * @return BelongsTo<DepartmentRequest, $this>
     */
    public function departmentRequest(): BelongsTo
    {
        return $this->belongsTo(DepartmentRequest::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function responsibleDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'responsible_department_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return HasOne<Submission, $this>
     */
    public function submission(): HasOne
    {
        return $this->hasOne(Submission::class);
    }

    /**
     * @return HasMany<WorkItemAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(WorkItemAssignment::class);
    }

    /**
     * @return HasMany<Finding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class);
    }

    /**
     * @return HasOne<WorkReopen, $this>
     */
    public function reopen(): HasOne
    {
        return $this->hasOne(WorkReopen::class);
    }
}
