<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'finding_id',
    'responsible_user_id',
    'sla_type',
    'effective_minutes',
    'started_at',
    'due_at',
    'completed_at',
    'breached_at',
    'status',
])]
class SlaInstance extends Model
{
    use HasUuids;

    public const TYPE_MANAGER = 'manager';

    public const TYPE_ISO = 'iso';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_BREACHED = 'breached';

    protected function casts(): array
    {
        return [
            'effective_minutes' => 'integer',
            'started_at' => 'datetime',
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'breached_at' => 'datetime',
        ];
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
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }
}
