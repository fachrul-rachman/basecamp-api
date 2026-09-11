<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Services\ComplianceScoreService;
use App\Services\Reporting\ReportingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * All endpoints return backend-calculated, ready-to-render metrics
 * (docs/06-API-CONTRACT.md §14), including the numeric compliance scores
 * (§15). Access follows the Resource Matrix "Reports" row
 * (docs/07-AUTHORIZATION.md §9): SuperAdmin/Admin/Director/ISO read
 * globally, Manager is scoped to their own department(s), PIC to their own
 * record only.
 */
class ReportController extends Controller
{
    public function __construct(
        private ReportingService $reports,
        private ComplianceScoreService $scores,
    ) {}

    public function company(Request $request)
    {
        $this->authorizeGlobal($request->user());

        [$from, $to] = $this->resolveWindow($request);

        return response()->json(['data' => $this->reports->companyMetrics($from, $to)]);
    }

    public function departments(Request $request)
    {
        $actor = $request->user();
        [$from, $to] = $this->resolveWindow($request);

        if ($this->hasGlobalAccess($actor)) {
            return response()->json(['data' => $this->reports->departmentsSummary($from, $to)]);
        }

        if ($actor->hasRole(Role::MANAGER)) {
            $data = $actor->departments->map(fn (Department $d) => $this->reports->departmentMetrics($d, $from, $to))->values();

            return response()->json(['data' => $data]);
        }

        abort(403);
    }

    public function department(Request $request, Department $department)
    {
        $this->authorizeDepartment($request->user(), $department->id);

        [$from, $to] = $this->resolveWindow($request);

        return response()->json(['data' => $this->reports->departmentDrillDown($department, $from, $to)]);
    }

    public function manager(Request $request, User $manager)
    {
        $this->authorizeSelfOrScoped($request->user(), $manager, Role::MANAGER);

        [$from, $to] = $this->resolveWindow($request);

        return response()->json(['data' => $this->reports->managerMetrics($manager, $from, $to)]);
    }

    public function pic(Request $request, User $pic)
    {
        $this->authorizeSelfOrScoped($request->user(), $pic, Role::PIC);

        [$from, $to] = $this->resolveWindow($request);

        return response()->json(['data' => $this->reports->picMetrics($pic, $from, $to)]);
    }

    public function managerScore(Request $request, User $manager)
    {
        $this->authorizeSelfOrScoped($request->user(), $manager, Role::MANAGER);

        $month = $this->resolveMonth($request);

        return response()->json(['data' => array_merge(
            ['subject_id' => $manager->id, 'period_month' => $month->toDateString()],
            $this->scores->managerScore($manager, $month)
        )], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function picScore(Request $request, User $pic)
    {
        $this->authorizeSelfOrScoped($request->user(), $pic, Role::PIC);

        $month = $this->resolveMonth($request);

        return response()->json(['data' => array_merge(
            ['subject_id' => $pic->id, 'period_month' => $month->toDateString()],
            $this->scores->picScore($pic, $month)
        )], 200, [], JSON_PRESERVE_ZERO_FRACTION);
    }

    public function patterns(Request $request)
    {
        $actor = $request->user();
        [$from, $to] = $this->resolveWindow($request);

        $filters = $request->only(['department_id', 'pic_id', 'checklist_id', 'finding_type']);

        if (! $this->hasGlobalAccess($actor)) {
            if ($actor->hasRole(Role::MANAGER)) {
                $departmentIds = $actor->departments->pluck('id');

                if (! empty($filters['department_id']) && ! $departmentIds->contains($filters['department_id'])) {
                    abort(403);
                }

                $filters['department_id'] = $filters['department_id'] ?? $departmentIds->first();
            } elseif ($actor->hasRole(Role::PIC)) {
                $filters['pic_id'] = $actor->id;
            } else {
                abort(403);
            }
        }

        return response()->json(['data' => $this->reports->patterns($from, $to, $filters)]);
    }

    public function iso(Request $request)
    {
        $this->authorizeGlobal($request->user());

        [$from, $to] = $this->resolveWindow($request);

        return response()->json(['data' => $this->reports->isoMetrics($from, $to)]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveWindow(Request $request): array
    {
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $to = $request->filled('date_to') ? Carbon::parse($request->query('date_to'))->endOfDay() : now();
            $from = $request->filled('date_from') ? Carbon::parse($request->query('date_from'))->startOfDay() : $to->copy()->subDays(30);

            return [$from, $to];
        }

        $days = (int) $request->query('days', 30);

        return [now()->subDays($days)->startOfDay(), now()];
    }

    private function resolveMonth(Request $request): Carbon
    {
        if (! $request->filled('month')) {
            return now()->startOfMonth();
        }

        $request->validate(['month' => ['date_format:Y-m']]);

        return Carbon::createFromFormat('!Y-m', $request->query('month'))->startOfMonth();
    }

    private function hasGlobalAccess(User $actor): bool
    {
        return $actor->hasAnyRole([Role::ADMIN, Role::DIRECTOR, Role::ISO]);
    }

    private function authorizeGlobal(User $actor): void
    {
        abort_unless($actor->hasAnyRole([Role::DIRECTOR, Role::ISO]), 403);
    }

    private function authorizeDepartment(User $actor, string $departmentId): void
    {
        if ($this->hasGlobalAccess($actor)) {
            return;
        }

        if ($actor->hasRole(Role::MANAGER) && $actor->departments->pluck('id')->contains($departmentId)) {
            return;
        }

        abort(403);
    }

    private function authorizeSelfOrScoped(User $actor, User $subject, string $subjectRole): void
    {
        if ($this->hasGlobalAccess($actor)) {
            return;
        }

        if ($actor->id === $subject->id) {
            return;
        }

        if ($subjectRole === Role::PIC && $actor->hasRole(Role::MANAGER)) {
            $sharedDepartment = $actor->departments->pluck('id')->intersect($subject->departments->pluck('id'))->isNotEmpty();
            if ($sharedDepartment) {
                return;
            }
        }

        abort(403);
    }
}
