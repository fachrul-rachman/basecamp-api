<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReassignWorkItemRequest;
use App\Http\Requests\ReviewEvidenceRequest;
use App\Http\Requests\StoreWorkItemEvidenceRequest;
use App\Http\Requests\StoreWorkItemSubmissionRequest;
use App\Http\Resources\WorkItemResource;
use App\Models\Evidence;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\EvidenceService;
use App\Services\SubmissionService;
use App\Services\WorkItemAssignmentService;
use App\Support\EvidenceDisk;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WorkItemController extends Controller
{
    public function __construct(
        private SubmissionService $submissions,
        private EvidenceService $evidenceService,
        private WorkItemAssignmentService $assignments,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', WorkItem::class);

        $query = $this->scopedQuery($request);

        if ($request->filled('date')) {
            $query->whereDate('operational_date', $request->query('date'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('operational_date', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('operational_date', '<=', $request->query('date_to'));
        }
        if ($request->filled('execution_status')) {
            $query->where('execution_status', $request->query('execution_status'));
        }
        if ($request->filled('compliance_status')) {
            $query->where('compliance_status', $request->query('compliance_status'));
        }
        if ($request->filled('task')) {
            $query->where('task_id', $request->query('task'));
        }
        if ($request->filled('checklist')) {
            $query->where('task_checklist_id', $request->query('checklist'));
        }
        if ($request->filled('department')) {
            $query->where('responsible_department_id', $request->query('department'));
        }
        if ($request->filled('assignee')) {
            $query->where('assignee_id', $request->query('assignee'));
        }

        return WorkItemResource::collection($query->with(['task', 'taskChecklist', 'submission.evidence'])->paginate());
    }

    public function today(Request $request)
    {
        $this->authorize('viewAny', WorkItem::class);

        $today = Carbon::today();
        $tomorrow = $today->copy()->addDay();

        $query = $this->scopedQuery($request)->where(function ($q) use ($today, $tomorrow) {
            $q->whereDate('operational_date', $today)
                ->orWhereDate('operational_date', $tomorrow)
                ->orWhere(fn ($pq) => $pq->whereNotNull('period_start')
                    ->whereDate('period_start', '<=', $today)
                    ->whereDate('period_end', '>=', $today))
                ->orWhere(fn ($pq) => $pq->whereNotNull('period_start')->whereDate('period_start', $tomorrow));
        });

        return WorkItemResource::collection($query->with(['task', 'taskChecklist', 'submission.evidence'])->get());
    }

    public function show(Request $request, WorkItem $workItem)
    {
        $this->authorize('view', $workItem);

        return new WorkItemResource($workItem->load(['task', 'taskChecklist', 'submission.evidence']));
    }

    public function storeSubmission(StoreWorkItemSubmissionRequest $request, WorkItem $workItem)
    {
        $submission = $this->submissions->save(
            $request->user(),
            $workItem,
            $request->validated('notes'),
            $request->boolean('submit')
        );

        return new WorkItemResource($workItem->fresh(['task', 'taskChecklist', 'submission.evidence']));
    }

    public function storeEvidence(StoreWorkItemEvidenceRequest $request, WorkItem $workItem)
    {
        $evidence = $this->evidenceService->upload(
            $request->user(),
            $workItem,
            $request->file('file'),
            $request->validated('source_type'),
            $request->validated('captured_at') ? Carbon::parse($request->validated('captured_at')) : null
        );

        return response()->json(['data' => [
            'id' => $evidence->id,
            'url' => EvidenceDisk::url($evidence->storage_key),
            'source_type' => $evidence->source_type,
            'captured_at' => $evidence->captured_at,
            'uploaded_at' => $evidence->uploaded_at,
        ]], 201);
    }

    public function destroyEvidence(Request $request, WorkItem $workItem, Evidence $evidence)
    {
        $this->authorize('submit', $workItem);
        abort_if($evidence->submission?->work_item_id !== $workItem->id, 404);

        $this->evidenceService->delete($workItem, $evidence);

        return response()->json(['data' => ['message' => 'Evidence removed.']]);
    }

    public function reviewEvidence(ReviewEvidenceRequest $request, WorkItem $workItem, Evidence $evidence)
    {
        abort_if($evidence->submission?->work_item_id !== $workItem->id, 404);

        $evidence = $this->evidenceService->review($evidence, $request->validated('status'));

        return response()->json(['data' => [
            'id' => $evidence->id,
            'human_review_status' => $evidence->human_review_status,
        ]]);
    }

    public function reassign(ReassignWorkItemRequest $request, WorkItem $workItem)
    {
        $pic = User::findOrFail($request->validated('pic_id'));

        $workItem = $this->assignments->reassign($request->user(), $workItem, $pic, $request->validated('reason'));

        return new WorkItemResource($workItem->load(['task', 'taskChecklist']));
    }

    private function scopedQuery(Request $request): Builder
    {
        $actor = $request->user();
        $query = WorkItem::query();

        if ($actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO])) {
            return $query;
        }

        if ($actor->hasRole(Role::MANAGER)) {
            $departmentIds = $actor->departments->pluck('id');

            return $query->where(
                fn ($q) => $q->whereIn('responsible_department_id', $departmentIds)
                    ->orWhereHas('task', fn ($tq) => $tq->whereIn('owner_department_id', $departmentIds))
            );
        }

        // Plain PIC: always scoped to their own assigned work, regardless
        // of query params (docs/06-API-CONTRACT.md §8: "PIC defaults to
        // own work").
        return $query->where('assignee_id', $actor->id);
    }
}
