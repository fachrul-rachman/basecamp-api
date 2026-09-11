<?php

namespace App\Models;

use Database\Factories\TaskTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'is_active', 'created_by'])]
class TaskTemplate extends Model
{
    /** @use HasFactory<TaskTemplateFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Departments allowed to use this template. Empty means available
     * company-wide (see docs/03-DOMAIN-MODEL.md §4).
     *
     * @return BelongsToMany<Department, $this>
     */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'task_template_departments');
    }

    /**
     * @return HasMany<TaskTemplateChecklist, $this>
     */
    public function checklists(): HasMany
    {
        return $this->hasMany(TaskTemplateChecklist::class)->orderBy('sort_order');
    }
}
