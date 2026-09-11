<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'task_template_id',
    'title',
    'instructions',
    'target_department_id',
    'schedule_type',
    'schedule_config',
    'evidence_min_count',
    'allow_upload',
    'allow_camera',
    'works_on_holidays',
    'sort_order',
])]
class TaskTemplateChecklist extends Model
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
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TaskTemplate, $this>
     */
    public function taskTemplate(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function targetDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }

    /**
     * @return HasMany<TemplateReferenceEvidence, $this>
     */
    public function referenceEvidence(): HasMany
    {
        return $this->hasMany(TemplateReferenceEvidence::class);
    }
}
