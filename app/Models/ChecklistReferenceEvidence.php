<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_checklist_id', 'storage_key', 'metadata'])]
class ChecklistReferenceEvidence extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<TaskChecklist, $this>
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(TaskChecklist::class, 'task_checklist_id');
    }
}
