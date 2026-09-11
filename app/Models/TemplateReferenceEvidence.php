<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_template_checklist_id', 'storage_key', 'metadata'])]
class TemplateReferenceEvidence extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<TaskTemplateChecklist, $this>
     */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(TaskTemplateChecklist::class, 'task_template_checklist_id');
    }
}
