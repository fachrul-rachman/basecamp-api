<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UpdateUserWorkCalendarRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\WorkingCalendarResource;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private UserService $users) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();
        $query = User::query()->with(['roles', 'departments']);

        if ($actor->hasRole(Role::MANAGER) && ! $actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO])) {
            $departmentIds = $actor->departments->pluck('id');
            $query->whereHas('departments', fn ($q) => $q->whereIn('departments.id', $departmentIds));
        }

        if ($request->filled('department')) {
            $query->whereHas('departments', fn ($q) => $q->where('departments.id', $request->query('department')));
        }

        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('code', $request->query('role')));
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
        }

        return UserResource::collection($query->paginate());
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->users->create($request->user(), $request->validated());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function show(Request $request, User $user)
    {
        $this->authorize('view', $user);

        return new UserResource($user->load(['roles', 'departments']));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $user = $this->users->update($request->user(), $user, $request->validated());

        return new UserResource($user);
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);

        $this->users->deactivate($request->user(), $user);

        return response()->json(['data' => ['message' => 'User deactivated.']]);
    }

    public function workCalendar(Request $request, User $user)
    {
        $this->authorize('view', $user);

        $calendar = $user->workingCalendar()->with(['hours', 'exceptions'])->first();

        return $calendar
            ? new WorkingCalendarResource($calendar)
            : response()->json(['data' => null]);
    }

    public function updateWorkCalendar(UpdateUserWorkCalendarRequest $request, User $user)
    {
        $user->update(['working_calendar_id' => $request->validated('working_calendar_id')]);

        return new WorkingCalendarResource(
            $user->workingCalendar()->with(['hours', 'exceptions'])->first()
        );
    }
}
