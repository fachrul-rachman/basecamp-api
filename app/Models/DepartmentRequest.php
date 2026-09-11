<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'task_id',
    'task_checklist_id',
    'owner_department_id',
    'target_department_id',
    'target_manager_id',
    'status',
    'response_due_at',
    'assigned_pic_id',
    'rejection_reason',
])]
class DepartmentRequest extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'response_due_at' => 'datetime',
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
     * @return BelongsTo<Department, $this>
     */
    public function ownerDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'owner_department_id');
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
    public function targetManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_manager_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedPic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_pic_id');
    }
}
