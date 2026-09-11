<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'old_schedule', 'new_schedule', 'reason', 'changed_by'])]
class TaskScheduleChange extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    const CREATED_AT = null;

    protected function casts(): array
    {
        return [
            'old_schedule' => 'array',
            'new_schedule' => 'array',
            'changed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
