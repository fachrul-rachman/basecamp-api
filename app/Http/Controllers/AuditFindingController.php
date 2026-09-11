<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuditFindingManagerResponseRequest;
use App\Http\Requests\AuditFindingReviewRequest;
use App\Http\Requests\StoreAuditFindingRequest;
use App\Http\Resources\FindingResource;
use App\Models\Finding;
use App\Models\Role;
use App\Services\AuditFindingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AuditFindingController extends Controller
{
    public function __construct(private AuditFindingService $auditFindings) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Finding::class);

        $actor = $request->user();
        $query = Finding::query()->where('source_type', Finding::SOURCE_ISO_MANUAL);

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

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('department')) {
            $query->where('target_department_id', $request->query('department'));
        }

        return FindingResource::collection($query->paginate());
    }

    public function store(StoreAuditFindingRequest $request)
    {
        $finding = $this->auditFindings->create($request->user(), $request->validated());

        return (new FindingResource($finding))->response()->setStatusCode(201);
    }

    public function show(Request $request, Finding $finding)
    {
        abort_if($finding->source_type !== Finding::SOURCE_ISO_MANUAL, 404);
        $this->authorize('view', $finding);

        return new FindingResource($finding->load(['actions', 'evidence']));
    }

    public function managerResponse(AuditFindingManagerResponseRequest $request, Finding $finding)
    {
        abort_if($finding->source_type !== Finding::SOURCE_ISO_MANUAL, 404);

        $finding = $this->auditFindings->respond(
            $request->user(),
            $finding,
            $request->validated('notes'),
            $request->file('evidence', [])
        );

        return new FindingResource($finding);
    }

    public function review(AuditFindingReviewRequest $request, Finding $finding)
    {
        abort_if($finding->source_type !== Finding::SOURCE_ISO_MANUAL, 404);

        $finding = $this->auditFindings->review(
            $request->user(),
            $finding,
            $request->validated('status'),
            $request->validated('notes'),
            $request->validated('due_at') ? Carbon::parse($request->validated('due_at')) : null
        );

        return new FindingResource($finding);
    }
}
