<?php

namespace App\Http\Controllers;

use App\Http\Requests\RescheduleTaskRequest;
use App\Http\Requests\StoreTaskChecklistRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskChecklistRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskChecklistResource;
use App\Http\Resources\TaskResource;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TaskController extends Controller
{
    public function __construct(private TaskService $tasks) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Task::class);

        $actor = $request->user();
        $query = Task::query()->with(['ownerDepartment', 'checklists.referenceEvidence', 'checklists.departmentRequests']);

        if (! $actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO])) {
            $departmentIds = $actor->departments->pluck('id');
            $query->where(
                fn ($q) => $q->whereIn('owner_department_id', $departmentIds)
                    ->orWhereHas('departmentRequests', fn ($dq) => $dq->whereIn('target_department_id', $departmentIds))
            );
        }

        if ($request->filled('owner_department')) {
            $query->where('owner_department_id', $request->query('owner_department'));
        }

        if ($request->filled('target_department')) {
            $departmentId = $request->query('target_department');
            $query->whereHas('departmentRequests', fn ($q) => $q->where('target_department_id', $departmentId));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('starts_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('starts_at', '<=', $request->query('date_to'));
        }

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->query('search').'%');
        }

        return TaskResource::collection($query->paginate());
    }

    public function store(StoreTaskRequest $request)
    {
        ['task' => $task, 'warnings' => $warnings] = $this->tasks->create($request->user(), $request->validated());

        return (new TaskResource($task))
            ->additional(['meta' => ['warnings' => $warnings]])
            ->response()->setStatusCode(201);
    }

    public function show(Request $request, Task $task)
    {
        $this->authorize('view', $task);

        return new TaskResource($task->load([
            'ownerDepartment', 'sourceTemplate', 'checklists.referenceEvidence', 'checklists.departmentRequests',
        ]));
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $task = $this->tasks->update($request->user(), $task, $request->validated());

        return new TaskResource($task);
    }

    public function reschedule(RescheduleTaskRequest $request, Task $task)
    {
        ['task' => $task, 'warnings' => $warnings] = $this->tasks->reschedule(
            $request->user(),
            $task,
            Carbon::parse($request->validated('starts_at')),
            $request->validated('ends_at') ? Carbon::parse($request->validated('ends_at')) : null,
            $request->validated('reason')
        );

        return (new TaskResource($task))->additional(['meta' => ['warnings' => $warnings]]);
    }

    public function cancel(Request $request, Task $task)
    {
        $this->authorize('cancel', $task);

        $task = $this->tasks->cancel($request->user(), $task);

        return new TaskResource($task);
    }

    public function checklists(Request $request, Task $task)
    {
        $this->authorize('view', $task);

        return TaskChecklistResource::collection(
            $task->checklists()->with(['referenceEvidence', 'departmentRequests'])->get()
        );
    }

    public function storeChecklist(StoreTaskChecklistRequest $request, Task $task)
    {
        [$checklist, $warnings] = $this->tasks->addChecklist($task, $request->validated());

        return (new TaskChecklistResource($checklist->load(['referenceEvidence', 'departmentRequests'])))
            ->additional(['meta' => ['warnings' => $warnings]])
            ->response()->setStatusCode(201);
    }

    public function updateChecklist(UpdateTaskChecklistRequest $request, Task $task, TaskChecklist $checklist)
    {
        abort_if($checklist->task_id !== $task->id, 404);

        $checklist->update($request->validated());

        return new TaskChecklistResource($checklist->fresh(['referenceEvidence', 'departmentRequests']));
    }

    public function destroyChecklist(Request $request, Task $task, TaskChecklist $checklist)
    {
        $this->authorize('manageChecklists', $task);
        abort_if($checklist->task_id !== $task->id, 404);

        $this->tasks->deactivateChecklist($checklist);

        return response()->json(['data' => ['message' => 'Checklist deactivated.']]);
    }
}
