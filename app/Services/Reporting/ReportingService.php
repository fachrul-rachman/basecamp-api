<?php

namespace App\Services\Reporting;

use App\Models\Department;
use App\Models\DepartmentRequest;
use App\Models\Finding;
use App\Models\FindingAction;
use App\Models\Role;
use App\Models\SlaInstance;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Backend-calculated, ready-to-render reporting aggregates
 * (docs/06-API-CONTRACT.md §14). No numeric score is computed here —
 * "Scoring Readiness" (§15) explicitly defers that to a later, approved
 * formula; these are the stable raw metrics it will be built on.
 */
class ReportingService
{
    public function companyMetrics(Carbon $from, Carbon $to): array
    {
        return array_merge(
            ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()],
            $this->windowMetrics(WorkItem::query(), Finding::query(), DepartmentRequest::query(), SlaInstance::query(), $from, $to)
        );
    }

    public function departmentsSummary(Carbon $from, Carbon $to): array
    {
        return Department::query()->orderBy('name')->get()->map(
            fn (Department $department) => $this->departmentMetrics($department, $from, $to)
        )->values()->all();
    }

    public function departmentMetrics(Department $department, Carbon $from, Carbon $to): array
    {
        return array_merge(
            [
                'department_id' => $department->id,
                'department_name' => $department->name,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
            $this->windowMetrics(
                WorkItem::where('responsible_department_id', $department->id),
                Finding::where('target_department_id', $department->id),
                DepartmentRequest::where(
                    fn (Builder $q) => $q->where('owner_department_id', $department->id)
                        ->orWhere('target_department_id', $department->id)
                ),
                SlaInstance::whereHas('finding', fn (Builder $q) => $q->where('target_department_id', $department->id)),
                $from,
                $to
            )
        );
    }

    public function departmentDrillDown(Department $department, Carbon $from, Carbon $to): array
    {
        $summary = $this->departmentMetrics($department, $from, $to);

        $members = $department->users()->with('roles')->get();

        $summary['managers'] = $members->filter(fn (User $u) => $u->hasRole(Role::MANAGER))
            ->map(fn (User $manager) => [
                'id' => $manager->id,
                'name' => $manager->name,
                'findings_handled' => FindingAction::where('actor_id', $manager->id)
                    ->whereBetween('created_at', [$from, $to])
                    ->distinct('finding_id')->count('finding_id'),
            ])->values();

        $summary['pics'] = $members->filter(fn (User $u) => $u->hasRole(Role::PIC))
            ->map(fn (User $pic) => [
                'id' => $pic->id,
                'name' => $pic->name,
                'work_items' => $this->workItemMetrics(WorkItem::where('assignee_id', $pic->id), $from, $to),
            ])->values();

        $summary['open_findings'] = Finding::where('target_department_id', $department->id)
            ->whereNotIn('status', [Finding::STATUS_RESOLVED, Finding::STATUS_CLOSED])
            ->latest('opened_at')
            ->limit(20)
            ->get(['id', 'source_type', 'finding_type', 'status', 'title', 'opened_at'])
            ->values();

        return $summary;
    }

    public function managerMetrics(User $manager, Carbon $from, Carbon $to): array
    {
        $handledFindingIds = FindingAction::where('actor_id', $manager->id)
            ->whereBetween('created_at', [$from, $to])
            ->pluck('finding_id')
            ->unique();

        $slaInstances = SlaInstance::where('responsible_user_id', $manager->id)
            ->where('sla_type', SlaInstance::TYPE_MANAGER)
            ->whereBetween('started_at', [$from, $to])
            ->get();

        $departmentIds = $manager->departments->pluck('id');

        return [
            'manager_id' => $manager->id,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'findings_handled_count' => $handledFindingIds->count(),
            'sla' => $this->slaBreakdown($slaInstances),
            'unresolved_findings' => Finding::whereIn('target_department_id', $departmentIds)
                ->whereNotIn('status', [Finding::STATUS_RESOLVED, Finding::STATUS_CLOSED])
                ->count(),
            'self_handled_findings' => Finding::where('target_user_id', $manager->id)
                ->where('is_self_handled', true)
                ->whereBetween('opened_at', [$from, $to])
                ->count(),
            'repeat_patterns' => $this->findingTypeBreakdown(
                Finding::whereIn('id', $handledFindingIds)
            ),
        ];
    }

    public function picMetrics(User $pic, Carbon $from, Carbon $to): array
    {
        $items = WorkItem::where('assignee_id', $pic->id)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        return [
            'pic_id' => $pic->id,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'work_items' => $this->summarizeWorkItems($items),
            'finding_count' => Finding::where('target_user_id', $pic->id)
                ->whereBetween('opened_at', [$from, $to])
                ->count(),
            'repeat_patterns' => Finding::where('target_user_id', $pic->id)
                ->whereBetween('opened_at', [$from, $to])
                ->selectRaw('task_checklist_id, count(*) as count')
                ->groupBy('task_checklist_id')
                ->havingRaw('count(*) > 1')
                ->get(),
        ];
    }

    /**
     * @return array{
     *     by_pic_and_checklist: Collection, by_pic_and_type: Collection,
     *     by_checklist: Collection, by_manager_sla_breach: Collection, by_department: Collection
     * }
     */
    public function patterns(Carbon $from, Carbon $to, array $filters): array
    {
        $findings = Finding::query()->whereBetween('opened_at', [$from, $to]);
        $this->applyPatternFilters($findings, $filters);

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            // Same PIC + same checklist.
            'by_pic_and_checklist' => (clone $findings)
                ->selectRaw('target_user_id, task_checklist_id, count(*) as count')
                ->whereNotNull('target_user_id')->whereNotNull('task_checklist_id')
                ->groupBy('target_user_id', 'task_checklist_id')
                ->havingRaw('count(*) > 1')->get(),
            // Same PIC + same finding type.
            'by_pic_and_type' => (clone $findings)
                ->selectRaw('target_user_id, finding_type, count(*) as count')
                ->whereNotNull('target_user_id')
                ->groupBy('target_user_id', 'finding_type')
                ->havingRaw('count(*) > 1')->get(),
            // Same checklist across many PICs.
            'by_checklist' => (clone $findings)
                ->selectRaw('task_checklist_id, count(distinct target_user_id) as distinct_pics, count(*) as count')
                ->whereNotNull('task_checklist_id')
                ->groupBy('task_checklist_id')
                ->havingRaw('count(*) > 1')->get(),
            // Same Manager + repeated SLA/handling issues.
            'by_manager_sla_breach' => SlaInstance::query()
                ->where('sla_type', SlaInstance::TYPE_MANAGER)
                ->where('status', SlaInstance::STATUS_BREACHED)
                ->whereBetween('started_at', [$from, $to])
                ->selectRaw('responsible_user_id, count(*) as count')
                ->groupBy('responsible_user_id')
                ->havingRaw('count(*) > 1')->get(),
            // Same Department over time.
            'by_department' => (clone $findings)
                ->selectRaw('target_department_id, count(*) as count')
                ->groupBy('target_department_id')
                ->havingRaw('count(*) > 1')->get(),
        ];
    }

    public function isoMetrics(Carbon $from, Carbon $to): array
    {
        $isoInstances = SlaInstance::where('sla_type', SlaInstance::TYPE_ISO)
            ->whereBetween('started_at', [$from, $to])
            ->get();

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'open_escalations' => Finding::where('status', Finding::STATUS_ESCALATED)->count(),
            'iso_sla' => $this->slaBreakdown($isoInstances),
            'manual_audits' => [
                'total' => Finding::where('source_type', Finding::SOURCE_ISO_MANUAL)
                    ->whereBetween('opened_at', [$from, $to])->count(),
                'open' => Finding::where('source_type', Finding::SOURCE_ISO_MANUAL)
                    ->where('status', Finding::STATUS_OPEN)->count(),
                'closed' => Finding::where('source_type', Finding::SOURCE_ISO_MANUAL)
                    ->where('status', Finding::STATUS_CLOSED)->count(),
                'info' => Finding::where('source_type', Finding::SOURCE_ISO_MANUAL)
                    ->where('status', Finding::STATUS_INFO)->count(),
            ],
            'unresolved_aged_cases' => Finding::whereNotIn('status', [Finding::STATUS_RESOLVED, Finding::STATUS_CLOSED])
                ->where('opened_at', '<=', now()->subDays(7))
                ->count(),
        ];
    }

    private function applyPatternFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['department_id'])) {
            $query->where('target_department_id', $filters['department_id']);
        }
        if (! empty($filters['pic_id'])) {
            $query->where('target_user_id', $filters['pic_id']);
        }
        if (! empty($filters['checklist_id'])) {
            $query->where('task_checklist_id', $filters['checklist_id']);
        }
        if (! empty($filters['finding_type'])) {
            $query->where('finding_type', $filters['finding_type']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function windowMetrics(Builder $workItems, Builder $findings, Builder $departmentRequests, Builder $slaInstances, Carbon $from, Carbon $to): array
    {
        return [
            'work_items' => $this->workItemMetrics((clone $workItems), $from, $to),
            'findings' => $this->findingMetrics((clone $findings), $from, $to),
            'department_requests' => $this->departmentRequestMetrics((clone $departmentRequests), $from, $to),
            'sla' => $this->slaBreakdown((clone $slaInstances)->whereBetween('started_at', [$from, $to])->get()),
        ];
    }

    private function workItemMetrics(Builder $query, Carbon $from, Carbon $to): array
    {
        return $this->summarizeWorkItems($query->whereBetween('created_at', [$from, $to])->get());
    }

    /**
     * @param  Collection<int, WorkItem>  $items
     */
    private function summarizeWorkItems(Collection $items): array
    {
        return [
            'total' => $items->count(),
            'on_time' => $items->where('compliance_status', WorkItem::COMPLIANCE_ON_TIME)->count(),
            'late' => $items->where('compliance_status', WorkItem::COMPLIANCE_LATE)->count(),
            'failed' => $items->where('compliance_status', WorkItem::COMPLIANCE_FAILED)->count(),
            'pending' => $items->where('compliance_status', WorkItem::COMPLIANCE_PENDING)->count(),
            'not_applicable' => $items->where('compliance_status', WorkItem::COMPLIANCE_NOT_APPLICABLE)->count(),
        ];
    }

    private function findingMetrics(Builder $query, Carbon $from, Carbon $to): array
    {
        $findings = $query->whereBetween('opened_at', [$from, $to])->get();

        return [
            'total' => $findings->count(),
            'open' => $findings->whereIn('status', [
                Finding::STATUS_OPEN, Finding::STATUS_WAITING_MANAGER_ACTION, Finding::STATUS_WAITING_ISO_REVIEW, Finding::STATUS_REOPENED,
            ])->count(),
            'resolved' => $findings->whereIn('status', [Finding::STATUS_RESOLVED, Finding::STATUS_CLOSED])->count(),
            'escalated' => $findings->where('status', Finding::STATUS_ESCALATED)->count(),
            'self_handled' => $findings->where('is_self_handled', true)->count(),
        ];
    }

    private function departmentRequestMetrics(Builder $query, Carbon $from, Carbon $to): array
    {
        $requests = $query->whereBetween('created_at', [$from, $to])->get();

        return [
            'total' => $requests->count(),
            'pending' => $requests->where('status', DepartmentRequest::STATUS_PENDING)->count(),
            'assigned' => $requests->where('status', DepartmentRequest::STATUS_ASSIGNED)->count(),
            'rejected' => $requests->where('status', DepartmentRequest::STATUS_REJECTED)->count(),
        ];
    }

    /**
     * @param  Collection<int, SlaInstance>  $instances
     */
    private function slaBreakdown(Collection $instances): array
    {
        $completed = $instances->where('status', SlaInstance::STATUS_COMPLETED);

        return [
            'total' => $instances->count(),
            'running' => $instances->where('status', SlaInstance::STATUS_RUNNING)->count(),
            'completed' => $completed->count(),
            'breached' => $instances->where('status', SlaInstance::STATUS_BREACHED)->count(),
            'average_resolution_minutes' => $completed->isEmpty() ? null : (int) round(
                $completed->avg(fn (SlaInstance $i) => $i->started_at->diffInMinutes($i->completed_at))
            ),
        ];
    }

    private function findingTypeBreakdown(Builder $query): Collection
    {
        return $query->selectRaw('finding_type, count(*) as count')->groupBy('finding_type')->get();
    }
}
