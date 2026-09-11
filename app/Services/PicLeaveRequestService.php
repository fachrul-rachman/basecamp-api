<?php

namespace App\Services;

use App\Models\Finding;
use App\Models\PicLeaveRequest;
use App\Models\User;
use App\Models\WorkItem;
use App\Notifications\PicLeaveRequestEvent;
use App\Support\EvidenceDisk;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manager-submitted, ISO-reviewed individual PIC leave requests
 * (docs/superpowers/specs/2026-09-09-pic-leave-request-design.md).
 */
class PicLeaveRequestService
{
    public function __construct(
        private AuditLogService $auditLog,
        private FindingService $findings,
        private NotificationDispatcher $notifications,
    ) {}

    public function create(
        User $actor,
        User $pic,
        CarbonInterface $dateFrom,
        CarbonInterface $dateTo,
        string $reason,
        ?UploadedFile $file
    ): PicLeaveRequest {
        if ($dateTo->toDateString() < $dateFrom->toDateString()) {
            throw ValidationException::withMessages([
                'date_to' => ['The end date must not be before the start date.'],
            ]);
        }

        $overlaps = PicLeaveRequest::query()
            ->where('pic_id', $pic->id)
            ->whereIn('status', [PicLeaveRequest::STATUS_PENDING, PicLeaveRequest::STATUS_APPROVED])
            // whereDate (not a bare where) — date_from/date_to are stored
            // with a midnight time suffix, so a plain string comparison
            // silently misses same-day boundary overlaps.
            ->whereDate('date_from', '<=', $dateTo->toDateString())
            ->whereDate('date_to', '>=', $dateFrom->toDateString())
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'pic_id' => ['This PIC already has a pending or approved leave request overlapping these dates.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $pic, $dateFrom, $dateTo, $reason, $file) {
            $data = [
                'pic_id' => $pic->id,
                'requested_by' => $actor->id,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'reason' => $reason,
                'status' => PicLeaveRequest::STATUS_PENDING,
            ];

            if ($file) {
                $data['storage_key'] = $file->store('pic-leave-evidence', EvidenceDisk::name());
                $data['metadata'] = [
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }

            $leaveRequest = PicLeaveRequest::create($data);

            $this->auditLog->record($actor, 'pic_leave_request.created', 'PicLeaveRequest', $leaveRequest->id, [
                'pic_id' => $pic->id,
            ]);

            $this->notifications->notifyIso(new PicLeaveRequestEvent($leaveRequest, PicLeaveRequestEvent::SUBMITTED));

            return $leaveRequest;
        });
    }

    public function approve(User $actor, PicLeaveRequest $leaveRequest, ?string $notes): PicLeaveRequest
    {
        $this->assertPending($leaveRequest);

        return DB::transaction(function () use ($actor, $leaveRequest, $notes) {
            $leaveRequest->update([
                'status' => PicLeaveRequest::STATUS_APPROVED,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            $excused = $this->excuseWorkItems($leaveRequest);

            $this->auditLog->record($actor, 'pic_leave_request.approved', 'PicLeaveRequest', $leaveRequest->id, $excused);

            $fresh = $leaveRequest->fresh();
            $this->notifications->notifyUser($fresh->requestedBy, new PicLeaveRequestEvent($fresh, PicLeaveRequestEvent::APPROVED));
            $this->notifications->notifyUser($fresh->pic, new PicLeaveRequestEvent($fresh, PicLeaveRequestEvent::APPROVED));

            return $fresh;
        });
    }

    public function reject(User $actor, PicLeaveRequest $leaveRequest, ?string $notes): PicLeaveRequest
    {
        $this->assertPending($leaveRequest);

        return DB::transaction(function () use ($actor, $leaveRequest, $notes) {
            $leaveRequest->update([
                'status' => PicLeaveRequest::STATUS_REJECTED,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);

            $this->auditLog->record($actor, 'pic_leave_request.rejected', 'PicLeaveRequest', $leaveRequest->id);

            $fresh = $leaveRequest->fresh();
            $this->notifications->notifyUser($fresh->requestedBy, new PicLeaveRequestEvent($fresh, PicLeaveRequestEvent::REJECTED));
            $this->notifications->notifyUser($fresh->pic, new PicLeaveRequestEvent($fresh, PicLeaveRequestEvent::REJECTED));

            return $fresh;
        });
    }

    /**
     * Excuses every not-yet-completed Work Item still assigned to this PIC
     * whose date falls inside the approved range, and resolves any Finding
     * that already got created for one of them (retroactive-approval case).
     * A Work Item already reassigned away from this PIC is excluded by the
     * `assignee_id` filter below, so normal rules stay in force for its new
     * assignee with no extra branch needed.
     *
     * @return array{work_item_ids: array<int, string>, finding_ids: array<int, string>}
     */
    private function excuseWorkItems(PicLeaveRequest $leaveRequest): array
    {
        $dateFrom = $leaveRequest->date_from->toDateString();
        $dateTo = $leaveRequest->date_to->toDateString();

        $items = WorkItem::query()
            ->where('assignee_id', $leaveRequest->pic_id)
            ->whereNotIn('execution_status', [WorkItem::EXECUTION_COMPLETED, WorkItem::EXECUTION_CANCELLED])
            ->where(function ($query) use ($dateFrom, $dateTo) {
                $query
                    ->where(function ($dateQuery) use ($dateFrom, $dateTo) {
                        // date-based: compare on the calendar date only —
                        // the column is stored with a midnight time suffix,
                        // so a bare string whereBetween silently mismatches
                        // whenever the leave's last day equals the work
                        // item's operational_date (SQLite AND Postgres).
                        $dateQuery->whereNotNull('operational_date')
                            ->whereDate('operational_date', '>=', $dateFrom)
                            ->whereDate('operational_date', '<=', $dateTo);
                    })
                    ->orWhere(function ($eventQuery) use ($dateFrom, $dateTo) {
                        // event-type: no operational_date/period, matched
                        // on the calendar date it actually became active.
                        $eventQuery->whereNull('operational_date')
                            ->whereNull('period_start')
                            ->whereDate('available_at', '>=', $dateFrom)
                            ->whereDate('available_at', '<=', $dateTo);
                    })
                    ->orWhere(function ($periodQuery) use ($dateFrom, $dateTo) {
                        // weekly_quota: only excused if the *entire* period
                        // sits inside the leave range (see spec §3/§6).
                        $periodQuery->whereNotNull('period_start')
                            ->whereDate('period_start', '>=', $dateFrom)
                            ->whereDate('period_end', '<=', $dateTo);
                    });
            })
            ->with('findings')
            ->get();

        $workItemIds = [];
        $findingIds = [];

        foreach ($items as $item) {
            $item->update([
                'execution_status' => WorkItem::EXECUTION_CANCELLED,
                'compliance_status' => WorkItem::COMPLIANCE_NOT_APPLICABLE,
                'locked_at' => now(),
            ]);

            $workItemIds[] = $item->id;

            foreach ($item->findings as $finding) {
                if ($finding->status !== Finding::STATUS_RESOLVED) {
                    $this->findings->resolveDueToApprovedLeave($finding);
                    $findingIds[] = $finding->id;
                }
            }
        }

        return ['work_item_ids' => $workItemIds, 'finding_ids' => $findingIds];
    }

    private function assertPending(PicLeaveRequest $leaveRequest): void
    {
        if ($leaveRequest->status !== PicLeaveRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['This leave request has already been reviewed.'],
            ]);
        }
    }
}
