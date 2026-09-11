<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['work_item_id', 'finding_id', 'opened_by', 'opened_at', 'deadline_at', 'reason', 'completed_at', 'result'])]
class WorkReopen extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    const CREATED_AT = null;

    public const RESULT_COMPLETED = 'completed';

    public const RESULT_MISSED = 'missed';

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'deadline_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkItem, $this>
     */
    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    /**
     * @return BelongsTo<Finding, $this>
     */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }
}
