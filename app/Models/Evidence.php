<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'submission_id',
    'storage_key',
    'source_type',
    'captured_at',
    'uploaded_at',
    'metadata',
    'ai_status',
    'ai_score',
    'human_review_status',
    'ai_notes',
])]
class Evidence extends Model
{
    use HasUuids;

    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_CAMERA = 'camera';

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'uploaded_at' => 'datetime',
            'metadata' => 'array',
            'ai_score' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
