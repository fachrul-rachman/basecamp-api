<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['finding_id', 'storage_key', 'metadata', 'uploaded_at'])]
class FindingEvidence extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'uploaded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Finding, $this>
     */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }
}
