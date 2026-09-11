<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['subject_type', 'subject_id', 'period_month', 'score', 'deduction_count', 'locked_at'])]
class MonthlyScore extends Model
{
    use HasUuids;

    public const SUBJECT_PIC = 'pic';

    public const SUBJECT_MANAGER = 'manager';

    protected function casts(): array
    {
        return [
            'period_month' => 'date',
            'score' => 'decimal:2',
            'deduction_count' => 'integer',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id');
    }
}
