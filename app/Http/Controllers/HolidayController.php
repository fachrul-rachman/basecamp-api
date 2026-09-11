<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHolidayRequest;
use App\Http\Requests\UpdateHolidayRequest;
use App\Http\Resources\HolidayResource;
use App\Models\Holiday;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function __construct(private AuditLogService $auditLog) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Holiday::class);

        $query = Holiday::query()->orderBy('date');

        if ($request->filled('scope')) {
            $query->where('scope', $request->query('scope'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->query('date_to'));
        }

        return HolidayResource::collection($query->paginate());
    }

    public function store(StoreHolidayRequest $request)
    {
        $holiday = Holiday::create($request->validated());
        $this->auditLog->record($request->user(), 'holiday.created', 'Holiday', $holiday->id);

        return (new HolidayResource($holiday))->response()->setStatusCode(201);
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday)
    {
        $holiday->update($request->validated());
        $this->auditLog->record($request->user(), 'holiday.updated', 'Holiday', $holiday->id);

        return new HolidayResource($holiday);
    }

    public function destroy(Request $request, Holiday $holiday)
    {
        $this->authorize('delete', $holiday);

        $holiday->delete();
        $this->auditLog->record($request->user(), 'holiday.deleted', 'Holiday', $holiday->id);

        return response()->json(['data' => ['message' => 'Holiday removed.']]);
    }
}
