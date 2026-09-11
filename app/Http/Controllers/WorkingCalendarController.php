<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCalendarExceptionRequest;
use App\Http\Requests\StoreWorkingCalendarRequest;
use App\Http\Requests\UpdateWorkingCalendarRequest;
use App\Http\Resources\WorkingCalendarResource;
use App\Models\WorkingCalendar;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class WorkingCalendarController extends Controller
{
    public function __construct(private AuditLogService $auditLog) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', WorkingCalendar::class);

        return WorkingCalendarResource::collection(
            WorkingCalendar::query()->with(['hours', 'exceptions'])->paginate()
        );
    }

    public function store(StoreWorkingCalendarRequest $request)
    {
        $calendar = WorkingCalendar::create($request->safe()->except('hours'));

        foreach ($request->validated('hours', []) as $hour) {
            $calendar->hours()->create($hour);
        }

        $this->auditLog->record($request->user(), 'working_calendar.created', 'WorkingCalendar', $calendar->id);

        return (new WorkingCalendarResource($calendar->load(['hours', 'exceptions'])))
            ->response()->setStatusCode(201);
    }

    public function show(Request $request, WorkingCalendar $workingCalendar)
    {
        $this->authorize('view', $workingCalendar);

        return new WorkingCalendarResource($workingCalendar->load(['hours', 'exceptions']));
    }

    public function update(UpdateWorkingCalendarRequest $request, WorkingCalendar $workingCalendar)
    {
        $workingCalendar->update($request->safe()->except('hours'));

        if ($request->has('hours')) {
            foreach ($request->validated('hours') as $hour) {
                $workingCalendar->hours()->updateOrCreate(['weekday' => $hour['weekday']], $hour);
            }
        }

        $this->auditLog->record($request->user(), 'working_calendar.updated', 'WorkingCalendar', $workingCalendar->id);

        return new WorkingCalendarResource($workingCalendar->load(['hours', 'exceptions']));
    }

    public function destroy(Request $request, WorkingCalendar $workingCalendar)
    {
        $this->authorize('delete', $workingCalendar);

        $workingCalendar->update(['is_active' => false]);
        $this->auditLog->record($request->user(), 'working_calendar.deactivated', 'WorkingCalendar', $workingCalendar->id);

        return response()->json(['data' => ['message' => 'Working calendar deactivated.']]);
    }

    public function storeException(StoreCalendarExceptionRequest $request, WorkingCalendar $workingCalendar)
    {
        $exception = $workingCalendar->exceptions()->create($request->validated());

        $this->auditLog->record(
            $request->user(),
            'working_calendar.exception_added',
            'WorkingCalendar',
            $workingCalendar->id,
            ['exception_id' => $exception->id]
        );

        return (new WorkingCalendarResource($workingCalendar->load(['hours', 'exceptions'])))
            ->response()->setStatusCode(201);
    }

    public function destroyException(Request $request, WorkingCalendar $workingCalendar, string $exception)
    {
        $this->authorize('manageExceptions', $workingCalendar);

        $workingCalendar->exceptions()->where('id', $exception)->delete();

        $this->auditLog->record(
            $request->user(),
            'working_calendar.exception_removed',
            'WorkingCalendar',
            $workingCalendar->id,
            ['exception_id' => $exception]
        );

        return response()->json(['data' => ['message' => 'Calendar exception removed.']]);
    }
}
