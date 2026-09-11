<?php

namespace App\Models;

use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['date', 'name', 'scope', 'metadata'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory, HasUuids;

    public const SCOPE_COMPANY = 'company';

    public const SCOPE_NATIONAL = 'national';

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'metadata' => 'array',
        ];
    }
}
