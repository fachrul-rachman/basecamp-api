<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewPicLeaveRequestRequest;
use App\Http\Requests\StorePicLeaveRequestRequest;
use App\Http\Resources\PicLeaveRequestResource;
use App\Models\PicLeaveRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\PicLeaveRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PicLeaveRequestController extends Controller
{
    public function __construct(private PicLeaveRequestService $leaveRequests) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', PicLeaveRequest::class);

        $actor = $request->user();
        $query = PicLeaveRequest::query()->with(['pic', 'requestedBy']);

        if (! $actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO])) {
            if ($actor->hasRole(Role::MANAGER)) {
                $departmentIds = $actor->departments->pluck('id');
                $query->whereHas('pic.departments', fn ($q) => $q->whereIn('departments.id', $departmentIds));
            } else {
                $query->where('pic_id', $actor->id);
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return PicLeaveRequestResource::collection($query->paginate());
    }

    public function store(StorePicLeaveRequestRequest $request)
    {
        $leaveRequest = $this->leaveRequests->create(
            $request->user(),
            User::findOrFail($request->validated('pic_id')),
            Carbon::parse($request->validated('date_from')),
            Carbon::parse($request->validated('date_to')),
            $request->validated('reason'),
            $request->file('evidence')
        );

        return (new PicLeaveRequestResource($leaveRequest))->response()->setStatusCode(201);
    }

    public function show(Request $request, PicLeaveRequest $picLeaveRequest)
    {
        $this->authorize('view', $picLeaveRequest);

        return new PicLeaveRequestResource($picLeaveRequest);
    }

    public function approve(ReviewPicLeaveRequestRequest $request, PicLeaveRequest $picLeaveRequest)
    {
        $leaveRequest = $this->leaveRequests->approve($request->user(), $picLeaveRequest, $request->validated('notes'));

        return new PicLeaveRequestResource($leaveRequest);
    }

    public function reject(ReviewPicLeaveRequestRequest $request, PicLeaveRequest $picLeaveRequest)
    {
        $leaveRequest = $this->leaveRequests->reject($request->user(), $picLeaveRequest, $request->validated('notes'));

        return new PicLeaveRequestResource($leaveRequest);
    }
}
