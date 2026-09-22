<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'task_id',
    'source_template_checklist_id',
    'title',
    'instructions',
    'target_department_id',
    'schedule_type',
    'schedule_config',
    'evidence_min_count',
    'allow_upload',
    'allow_camera',
    'works_on_holidays',
    'is_active',
    'default_assignee_id',
])]
class TaskChecklist extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'schedule_config' => 'array',
            'evidence_min_count' => 'integer',
            'allow_upload' => 'boolean',
            'allow_camera' => 'boolean',
            'works_on_holidays' => 'boolean',
            'is_active' => 'boolean',
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
     * @return BelongsTo<TaskTemplateChecklist, $this>
     */
    public function sourceTemplateChecklist(): BelongsTo
    {
        return $this->belongsTo(TaskTemplateChecklist::class, 'source_template_checklist_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function targetDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }

    /**
     * @return HasMany<ChecklistReferenceEvidence, $this>
     */
    public function referenceEvidence(): HasMany
    {
        return $this->hasMany(ChecklistReferenceEvidence::class);
    }

    /**
     * @return HasMany<DepartmentRequest, $this>
     */
    public function departmentRequests(): HasMany
    {
        return $this->hasMany(DepartmentRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function defaultAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assignee_id');
    }

    /**
     * @return HasMany<ChecklistAssigneeOverride, $this>
     */
    public function assigneeOverrides(): HasMany
    {
        return $this->hasMany(ChecklistAssigneeOverride::class);
    }

    /**
     * @return HasMany<WorkItem, $this>
     */
    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }
}
