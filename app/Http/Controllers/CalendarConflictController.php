<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Scheduling\ScheduleConflictChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarConflictController extends Controller
{
    public function __construct(private ScheduleConflictChecker $checker) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'pic_id' => ['nullable', 'uuid', 'exists:users,id'],
            'works_on_holidays' => ['sometimes', 'boolean'],
        ]);

        $pic = ! empty($validated['pic_id']) ? User::find($validated['pic_id']) : null;

        $warnings = $this->checker->check(
            Carbon::parse($validated['starts_at']),
            Carbon::parse($validated['ends_at'] ?? $validated['starts_at']),
            $request->boolean('works_on_holidays'),
            $pic
        );

        return response()->json(['data' => ['warnings' => $warnings]]);
    }
}
