<?php

namespace App\Services;

use App\Models\Finding;
use App\Models\FindingAction;
use App\Models\Role;
use App\Models\User;
use App\Notifications\FindingEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ISO manual audit findings (docs/02-BUSINESS-RULES.md §17): a distinct
 * open/closed/info lifecycle, never the automatic-finding lifecycle from
 * Phase 7, even though both share the `findings` table.
 */
class AuditFindingService
{
    public function __construct(
        private AuditLogService $auditLog,
        private NotificationDispatcher $notifications,
    ) {}

    public function create(User $actor, array $data): Finding
    {
        $finding = Finding::create([
            'source_type' => Finding::SOURCE_ISO_MANUAL,
            'finding_type' => Finding::TYPE_ISO_MANUAL,
            'task_id' => $data['task_id'] ?? null,
            'task_checklist_id' => $data['task_checklist_id'] ?? null,
            'work_item_id' => $data['work_item_id'] ?? null,
            'source_evidence_id' => $data['source_evidence_id'] ?? null,
            'target_department_id' => $data['target_department_id'],
            'target_user_id' => $data['target_manager_id'] ?? null,
            'created_by' => $actor->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'],
            // "info" requires no corrective deadline unless explicitly
            // changed (docs/02-BUSINESS-RULES.md §17) — due_at is simply
            // whatever ISO supplied, never invented.
            'due_at' => $data['due_at'] ?? null,
            'opened_at' => now(),
        ]);

        $this->auditLog->record($actor, 'audit_finding.created', 'Finding', $finding->id, [
            'status' => $finding->status,
        ]);

        if ($finding->target_user_id) {
            $this->notifications->notifyUser(
                User::find($finding->target_user_id),
                new FindingEvent($finding, FindingEvent::AUDIT_ASSIGNED)
            );
        } else {
            $this->notifications->notifyManagersOfDepartment(
                $finding->target_department_id,
                new FindingEvent($finding, FindingEvent::AUDIT_ASSIGNED)
            );
        }

        return $finding;
    }

    /**
     * @param  UploadedFile[]  $files
     */
    public function respond(User $actor, Finding $finding, string $notes, array $files): Finding
    {
        $this->assertIsoManual($finding);

        return DB::transaction(function () use ($actor, $finding, $notes, $files) {
            foreach ($files as $file) {
                $path = $file->store('finding-evidence', 'public');

                $finding->evidence()->create([
                    'storage_key' => $path,
                    'uploaded_at' => now(),
                    'metadata' => [
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                    ],
                ]);
            }

            $finding->actions()->create([
                'actor_id' => $actor->id,
                'actor_role' => Role::MANAGER,
                'action_type' => FindingAction::MANAGER_RESPONSE,
                'notes' => $notes,
            ]);

            $this->auditLog->record($actor, 'audit_finding.manager_responded', 'Finding', $finding->id, []);

            $fresh = $finding->fresh(['actions', 'evidence']);
            $this->notifications->notifyUser(
                $fresh->created_by ? User::find($fresh->created_by) : null,
                new FindingEvent($fresh, FindingEvent::AUDIT_RESPONSE_AWAITING_REVIEW)
            );

            return $fresh;
        });
    }

    public function review(User $actor, Finding $finding, string $status, ?string $notes, ?Carbon $dueAt): Finding
    {
        $this->assertIsoManual($finding);

        $actionType = match ($status) {
            Finding::STATUS_CLOSED => FindingAction::CLOSE,
            Finding::STATUS_OPEN => FindingAction::REOPEN_FINDING,
            Finding::STATUS_INFO => FindingAction::MARK_INFO,
            default => throw ValidationException::withMessages(['status' => ['Invalid status.']]),
        };

        return DB::transaction(function () use ($actor, $finding, $status, $notes, $dueAt, $actionType) {
            $finding->actions()->create([
                'actor_id' => $actor->id,
                'actor_role' => Role::ISO,
                'action_type' => $actionType,
                'notes' => $notes,
            ]);

            $finding->update([
                'status' => $status,
                'due_at' => $dueAt ?? $finding->due_at,
                'resolved_at' => $status === Finding::STATUS_CLOSED ? now() : null,
            ]);

            $this->auditLog->record($actor, 'audit_finding.reviewed', 'Finding', $finding->id, ['status' => $status]);

            $fresh = $finding->fresh(['actions', 'evidence']);
            $this->notifications->notifyUser(
                $fresh->target_user_id ? User::find($fresh->target_user_id) : null,
                new FindingEvent($fresh, FindingEvent::ISO_FEEDBACK)
            );

            return $fresh;
        });
    }

    private function assertIsoManual(Finding $finding): void
    {
        if ($finding->source_type !== Finding::SOURCE_ISO_MANUAL) {
            throw ValidationException::withMessages([
                'finding' => ['This action only applies to manual ISO audit findings.'],
            ]);
        }
    }
}
