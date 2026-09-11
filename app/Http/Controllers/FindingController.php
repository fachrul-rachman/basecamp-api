<?php

namespace App\Http\Controllers;

use App\Http\Requests\IsoReviewFindingRequest;
use App\Http\Requests\ReopenWorkItemFindingRequest;
use App\Http\Requests\StoreFindingExplanationRequest;
use App\Http\Resources\FindingActionResource;
use App\Http\Resources\FindingResource;
use App\Models\Finding;
use App\Models\Role;
use App\Models\SlaInstance;
use App\Services\IsoReviewService;
use App\Services\ManagerActionService;
use App\Services\Scheduling\WorkingTimeCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FindingController extends Controller
{
    public function __construct(
        private ManagerActionService $managerActions,
        private IsoReviewService $isoReview,
        private WorkingTimeCalculator $calculator,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Finding::class);

        $actor = $request->user();
        $query = Finding::query();

        if (! $actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO])) {
            if ($actor->hasRole(Role::MANAGER)) {
                $departmentIds = $actor->departments->pluck('id');
                $query->where(
                    fn ($q) => $q->whereIn('target_department_id', $departmentIds)->orWhere('target_user_id', $actor->id)
                );
            } else {
                $query->where('target_user_id', $actor->id);
            }
        }

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->query('source_type'));
        }
        if ($request->filled('finding_type')) {
            $query->where('finding_type', $request->query('finding_type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('department')) {
            $query->where('target_department_id', $request->query('department'));
        }
        if ($request->filled('user')) {
            $query->where('target_user_id', $request->query('user'));
        }
        if ($request->filled('task')) {
            $query->where('task_id', $request->query('task'));
        }
        if ($request->filled('self_handled')) {
            $query->where('is_self_handled', $request->boolean('self_handled'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('opened_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('opened_at', '<=', $request->query('date_to'));
        }

        return FindingResource::collection($query->paginate());
    }

    public function show(Request $request, Finding $finding)
    {
        $this->authorize('view', $finding);

        return new FindingResource($finding->load(['slaInstances', 'workItem', 'task', 'taskChecklist']));
    }

    public function explanation(StoreFindingExplanationRequest $request, Finding $finding)
    {
        $finding = $this->managerActions->explain($request->user(), $finding, $request->validated('notes'));

        return new FindingResource($finding);
    }

    public function reopenWork(ReopenWorkItemFindingRequest $request, Finding $finding)
    {
        $finding = $this->managerActions->reopen(
            $request->user(),
            $finding,
            Carbon::parse($request->validated('deadline_at')),
            $request->validated('reason')
        );

        return new FindingResource($finding);
    }

    public function isoReview(IsoReviewFindingRequest $request, Finding $finding)
    {
        $finding = $this->isoReview->review(
            $request->user(),
            $finding,
            $request->validated('decision'),
            $request->validated('notes')
        );

        return new FindingResource($finding);
    }

    public function timeline(Request $request, Finding $finding)
    {
        $this->authorize('view', $finding);

        return FindingActionResource::collection($finding->actions);
    }

    public function sla(Request $request, Finding $finding)
    {
        $this->authorize('view', $finding);

        $instance = $finding->slaInstances()->latest('started_at')->first();

        if (! $instance) {
            return response()->json(['data' => null]);
        }

        $remaining = null;

        if ($instance->status === SlaInstance::STATUS_RUNNING) {
            $calendar = $instance->responsibleUser?->workingCalendar()->with(['hours', 'exceptions'])->first();
            $elapsed = $calendar
                ? $this->calculator->elapsedWorkingMinutes($calendar, $instance->started_at, now())
                : (int) $instance->started_at->diffInMinutes(now());
            $remaining = max(0, $instance->effective_minutes - $elapsed);
        }

        return response()->json(['data' => [
            'status' => $instance->status,
            'effective_minutes' => $instance->effective_minutes,
            'started_at' => $instance->started_at,
            'due_at' => $instance->due_at,
            'remaining_work_minutes' => $remaining,
            'is_breached' => $instance->status === SlaInstance::STATUS_BREACHED,
        ]]);
    }
}
