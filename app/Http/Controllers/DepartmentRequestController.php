<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignDepartmentRequestRequest;
use App\Http\Requests\ReassignDepartmentRequestRequest;
use App\Http\Requests\RejectDepartmentRequestRequest;
use App\Http\Resources\DepartmentRequestResource;
use App\Models\DepartmentRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\DepartmentRequestService;
use Illuminate\Http\Request;

class DepartmentRequestController extends Controller
{
    public function __construct(private DepartmentRequestService $departmentRequests) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', DepartmentRequest::class);

        $actor = $request->user();
        $query = DepartmentRequest::query();

        if (! $actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO])) {
            $departmentIds = $actor->departments->pluck('id');
            $query->where(
                fn ($q) => $q->whereIn('owner_department_id', $departmentIds)
                    ->orWhereIn('target_department_id', $departmentIds)
            );
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('task')) {
            $query->where('task_id', $request->query('task'));
        }

        if ($request->filled('department')) {
            $departmentId = $request->query('department');
            $query->where(
                fn ($q) => $q->where('owner_department_id', $departmentId)->orWhere('target_department_id', $departmentId)
            );
        }

        return DepartmentRequestResource::collection($query->paginate());
    }

    public function show(Request $request, DepartmentRequest $departmentRequest)
    {
        $this->authorize('view', $departmentRequest);

        return new DepartmentRequestResource($departmentRequest);
    }

    public function assign(AssignDepartmentRequestRequest $request, DepartmentRequest $departmentRequest)
    {
        $pic = User::findOrFail($request->validated('pic_id'));

        $departmentRequest = $this->departmentRequests->assign($request->user(), $departmentRequest, $pic);

        return new DepartmentRequestResource($departmentRequest);
    }

    public function reject(RejectDepartmentRequestRequest $request, DepartmentRequest $departmentRequest)
    {
        $departmentRequest = $this->departmentRequests->reject(
            $request->user(), $departmentRequest, $request->validated('reason')
        );

        return new DepartmentRequestResource($departmentRequest);
    }

    public function reassign(ReassignDepartmentRequestRequest $request, DepartmentRequest $departmentRequest)
    {
        $pic = User::findOrFail($request->validated('pic_id'));

        $departmentRequest = $this->departmentRequests->reassign(
            $request->user(), $departmentRequest, $pic, $request->validated('reason')
        );

        return new DepartmentRequestResource($departmentRequest);
    }
}
