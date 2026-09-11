<?php

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'source_template_id',
    'owner_department_id',
    'created_by',
    'title',
    'description',
    'starts_at',
    'ends_at',
    'overall_deadline_at',
    'status',
    'schedule_snapshot',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasUuids;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'overall_deadline_at' => 'datetime',
            'schedule_snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<TaskTemplate, $this>
     */
    public function sourceTemplate(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'source_template_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function ownerDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'owner_department_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<TaskChecklist, $this>
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(TaskChecklist::class);
    }

    /**
     * @return HasMany<TaskScheduleChange, $this>
     */
    public function scheduleChanges(): HasMany
    {
        return $this->hasMany(TaskScheduleChange::class);
    }

    /**
     * @return HasMany<DepartmentRequest, $this>
     */
    public function departmentRequests(): HasMany
    {
        return $this->hasMany(DepartmentRequest::class);
    }
}
