<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentMembersRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\UserResource;
use App\Models\Department;
use App\Models\Role;
use App\Services\AuditLogService;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function __construct(private AuditLogService $auditLog) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Department::class);

        $actor = $request->user();
        $query = Department::query();

        if (! $actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO])) {
            $query->whereHas('users', fn ($q) => $q->where('users.id', $actor->id));
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->query('search').'%');
        }

        return DepartmentResource::collection($query->paginate());
    }

    public function store(StoreDepartmentRequest $request)
    {
        $department = Department::create($request->validated());
        $this->auditLog->record($request->user(), 'department.created', 'Department', $department->id);

        return (new DepartmentResource($department))->response()->setStatusCode(201);
    }

    public function show(Request $request, Department $department)
    {
        $this->authorize('view', $department);

        return new DepartmentResource($department);
    }

    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        $department->update($request->validated());
        $this->auditLog->record($request->user(), 'department.updated', 'Department', $department->id);

        return new DepartmentResource($department);
    }

    public function destroy(Request $request, Department $department)
    {
        $this->authorize('delete', $department);

        $department->update(['is_active' => false]);
        $this->auditLog->record($request->user(), 'department.deactivated', 'Department', $department->id);

        return response()->json(['data' => ['message' => 'Department deactivated.']]);
    }

    public function members(Request $request, Department $department)
    {
        $this->authorize('view', $department);

        return UserResource::collection($department->users()->with('roles')->get());
    }

    public function updateMembers(UpdateDepartmentMembersRequest $request, Department $department)
    {
        $members = collect($request->validated('members'))->mapWithKeys(fn ($member) => [
            $member['user_id'] => ['is_primary' => $member['is_primary'] ?? false],
        ]);

        $department->users()->sync($members);

        $this->auditLog->record($request->user(), 'department.members_updated', 'Department', $department->id, [
            'user_ids' => $members->keys()->all(),
        ]);

        return UserResource::collection($department->users()->with('roles')->get());
    }
}
