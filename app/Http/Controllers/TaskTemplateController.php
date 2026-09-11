<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskTemplateRequest;
use App\Http\Requests\StoreTemplateReferenceEvidenceRequest;
use App\Http\Requests\UpdateTaskTemplateRequest;
use App\Http\Resources\TaskTemplateResource;
use App\Http\Resources\TemplateReferenceEvidenceResource;
use App\Models\Role;
use App\Models\TaskTemplate;
use App\Models\TemplateReferenceEvidence;
use App\Services\AuditLogService;
use App\Services\TaskTemplateService;
use App\Support\EvidenceDisk;
use Illuminate\Http\Request;

class TaskTemplateController extends Controller
{
    public function __construct(
        private TaskTemplateService $templates,
        private AuditLogService $auditLog,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', TaskTemplate::class);

        $actor = $request->user();
        $query = TaskTemplate::query()->with('departments');

        if (! $actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO])) {
            $departmentIds = $actor->departments->pluck('id');
            $query->where('is_active', true)->where(
                fn ($q) => $q->whereDoesntHave('departments')
                    ->orWhereHas('departments', fn ($dq) => $dq->whereIn('departments.id', $departmentIds))
            );
        }

        if ($request->filled('available_for_department')) {
            $departmentId = $request->query('available_for_department');
            $query->where(
                fn ($q) => $q->whereDoesntHave('departments')
                    ->orWhereHas('departments', fn ($dq) => $dq->where('departments.id', $departmentId))
            );
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->query('search').'%');
        }

        return TaskTemplateResource::collection($query->paginate());
    }

    public function store(StoreTaskTemplateRequest $request)
    {
        $template = $this->templates->create($request->user(), $request->validated());

        return (new TaskTemplateResource($template))->response()->setStatusCode(201);
    }

    public function show(Request $request, TaskTemplate $template)
    {
        $this->authorize('view', $template);

        return new TaskTemplateResource(
            $template->load(['departments', 'checklists.referenceEvidence', 'createdBy'])
        );
    }

    public function update(UpdateTaskTemplateRequest $request, TaskTemplate $template)
    {
        $template = $this->templates->update($request->user(), $template, $request->validated());

        return new TaskTemplateResource($template);
    }

    public function destroy(Request $request, TaskTemplate $template)
    {
        $this->authorize('delete', $template);

        $this->templates->deactivate($request->user(), $template);

        return response()->json(['data' => ['message' => 'Template deactivated.']]);
    }

    public function storeReferenceEvidence(StoreTemplateReferenceEvidenceRequest $request, TaskTemplate $template)
    {
        $checklist = $template->checklists()->findOrFail($request->validated('task_template_checklist_id'));

        $path = $request->file('file')->store('template-reference-evidence', EvidenceDisk::name());

        $evidence = $checklist->referenceEvidence()->create([
            'storage_key' => $path,
            'metadata' => [
                'original_name' => $request->file('file')->getClientOriginalName(),
                'mime_type' => $request->file('file')->getClientMimeType(),
                'size' => $request->file('file')->getSize(),
            ],
        ]);

        $this->auditLog->record(
            $request->user(),
            'template_reference_evidence.added',
            'TaskTemplate',
            $template->id,
            ['evidence_id' => $evidence->id]
        );

        return (new TemplateReferenceEvidenceResource($evidence))->response()->setStatusCode(201);
    }

    public function destroyReferenceEvidence(Request $request, TaskTemplate $template, string $evidence)
    {
        $this->authorize('manageReferenceEvidence', $template);

        $row = TemplateReferenceEvidence::query()
            ->whereHas('checklist', fn ($q) => $q->where('task_template_id', $template->id))
            ->findOrFail($evidence);

        EvidenceDisk::disk()->delete($row->storage_key);
        $row->delete();

        $this->auditLog->record(
            $request->user(),
            'template_reference_evidence.removed',
            'TaskTemplate',
            $template->id,
            ['evidence_id' => $evidence]
        );

        return response()->json(['data' => ['message' => 'Reference evidence removed.']]);
    }
}
