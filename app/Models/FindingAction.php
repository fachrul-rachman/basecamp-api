<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['finding_id', 'actor_id', 'actor_role', 'action_type', 'notes', 'metadata'])]
class FindingAction extends Model
{
    use HasUuids;

    public const EXPLANATION = 'explanation';

    public const REOPEN = 'reopen';

    public const MANAGER_RESPONSE = 'manager_response';

    public const ISO_REVIEW = 'iso_review';

    public const CLOSE = 'close';

    public const REOPEN_FINDING = 'reopen_finding';

    public const MARK_INFO = 'mark_info';

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
