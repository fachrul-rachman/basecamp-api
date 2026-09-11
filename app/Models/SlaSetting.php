<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['scope_type', 'scope_id', 'minutes', 'is_active'])]
class SlaSetting extends Model
{
    use HasUuids;

    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_DEPARTMENT = 'department';

    public const SCOPE_MANAGER = 'manager';

    public const SCOPE_ISO = 'iso';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
