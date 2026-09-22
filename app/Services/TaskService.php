<?php

namespace App\Services;

use App\Models\DepartmentRequest;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Notifications\DepartmentRequestEvent;
use App\Services\Scheduling\ScheduleConflictChecker;
use App\Services\Scheduling\WorkItemGenerator;
use App\Support\EvidenceDisk;
use App\Support\Scheduling\ScheduleType;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TaskService
{
    public function __construct(
        private AuditLogService $auditLog,
        private ScheduleConflictChecker $conflictChecker,
        private WorkItemGenerator $workItemGenerator,
        private NotificationDispatcher $notifications,
    ) {}

    /**
     * @return array{task: Task, warnings: array}
     */
    public function create(User $actor, array $data): array
    {
        return DB::transaction(function () use ($actor, $data) {
            $template = ! empty($data['template_id'])
                ? TaskTemplate::with('checklists.referenceEvidence')->findOrFail($data['template_id'])
                : null;

            $task = Task::create([
                'source_template_id' => $template?->id,
                'owner_department_id' => $data['owner_department_id'],
                'created_by' => $actor->id,
                'title' => $data['title'] ?? $template?->name,
                'description' => $data['description'] ?? $template?->description,
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'] ?? null,
                'overall_deadline_at' => $data['overall_deadline_at'] ?? null,
                'status' => Task::STATUS_ACTIVE,
            ]);

            $warnings = [];

            foreach ($this->resolveChecklistInputs($data, $template) as $checklistInput) {
                [, $checklistWarnings] = $this->addChecklist($task, $checklistInput);
                $warnings = array_merge($warnings, $checklistWarnings);
            }

            $this->auditLog->record($actor, 'task.created', 'Task', $task->id, ['template_id' => $template?->id]);

            return [
                'task' => $task->load(['ownerDepartment', 'sourceTemplate', 'checklists.referenceEvidence', 'checklists.departmentRequests', 'checklists.assigneeOverrides']),
                'warnings' => $warnings,
            ];
        });
    }

    public function update(User $actor, Task $task, array $data): Task
    {
        $task->fill(array_intersect_key($data, array_flip(['title', 'description', 'overall_deadline_at'])))->save();
        $this->auditLog->record($actor, 'task.updated', 'Task', $task->id);

        return $task->fresh(['ownerDepartment', 'checklists.departmentRequests', 'checklists.assigneeOverrides']);
    }

    /**
     * @return array{task: Task, warnings: array}
     */
    public function reschedule(User $actor, Task $task, CarbonInterface $startsAt, ?CarbonInterface $endsAt, ?string $reason): array
    {
        return DB::transaction(function () use ($actor, $task, $startsAt, $endsAt, $reason) {
            $task->scheduleChanges()->create([
                'old_schedule' => [
                    'starts_at' => $task->starts_at?->toIso8601String(),
                    'ends_at' => $task->ends_at?->toIso8601String(),
                ],
                'new_schedule' => [
                    'starts_at' => $startsAt->toIso8601String(),
                    'ends_at' => $endsAt?->toIso8601String(),
                ],
                'reason' => $reason,
                'changed_by' => $actor->id,
            ]);

            $task->update(['starts_at' => $startsAt, 'ends_at' => $endsAt]);

            $warnings = [];
            foreach ($task->checklists as $checklist) {
                $warnings = array_merge(
                    $warnings,
                    $this->conflictChecker->check($startsAt, $endsAt ?? $startsAt, $checklist->works_on_holidays)
                );
            }

            $this->auditLog->record($actor, 'task.rescheduled', 'Task', $task->id, ['reason' => $reason]);

            return ['task' => $task->fresh(['ownerDepartment', 'checklists.departmentRequests', 'checklists.assigneeOverrides']), 'warnings' => $warnings];
        });
    }

    public function cancel(User $actor, Task $task): Task
    {
        $task->update(['status' => Task::STATUS_CANCELLED]);
        $this->auditLog->record($actor, 'task.cancelled', 'Task', $task->id);

        return $task;
    }

    /**
     * @return array{0: TaskChecklist, 1: array<int, array>} [checklist, warnings]
     */
    public function addChecklist(Task $task, array $data): array
    {
        $targetDepartmentId = $data['target_department_id'] ?? $task->owner_department_id;

        $checklist = $task->checklists()->create([
            'source_template_checklist_id' => $data['source_template_checklist_id'] ?? null,
            'title' => $data['title'],
            'instructions' => $data['instructions'] ?? null,
            'target_department_id' => $targetDepartmentId,
            'schedule_type' => $data['schedule_type'],
            'schedule_config' => $data['schedule_config'] ?? [],
            'evidence_min_count' => $data['evidence_min_count'] ?? 0,
            'allow_upload' => $data['allow_upload'] ?? true,
            'allow_camera' => $data['allow_camera'] ?? true,
            'works_on_holidays' => $data['works_on_holidays'] ?? false,
        ]);

        // Physically copy reference evidence files so a later edit/removal
        // on the Template's own evidence can never affect this Task's
        // snapshot (see docs/03-DOMAIN-MODEL.md §4, Phase 4 acceptance).
        foreach ($data['reference_evidence'] ?? [] as $evidence) {
            $extension = pathinfo($evidence->storage_key, PATHINFO_EXTENSION);
            $newPath = 'checklist-reference-evidence/'.Str::uuid().($extension ? ".{$extension}" : '');
            EvidenceDisk::disk()->copy($evidence->storage_key, $newPath);

            $checklist->referenceEvidence()->create([
                'storage_key' => $newPath,
                'metadata' => $evidence->metadata,
            ]);
        }

        if ($targetDepartmentId !== $task->owner_department_id) {
            $this->createDepartmentRequest($task, $checklist);
        } elseif ($checklist->schedule_type === ScheduleType::Event->value) {
            // Same-department event work has no Department Request to wait
            // on, so its Work Item is created right away (unassigned, for
            // the owner Manager to assign via /work-items/{item}/reassign).
            $this->workItemGenerator->generateForEvent($checklist, null, null);
        }

        $warnings = $this->conflictChecker->check(
            $task->starts_at,
            $task->ends_at ?? $task->starts_at,
            $checklist->works_on_holidays
        );

        return [$checklist, $warnings];
    }

    public function deactivateChecklist(TaskChecklist $checklist): void
    {
        $checklist->update(['is_active' => false]);

        $checklist->departmentRequests()
            ->where('status', DepartmentRequest::STATUS_PENDING)
            ->update(['status' => DepartmentRequest::STATUS_CANCELLED]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveChecklistInputs(array $data, ?TaskTemplate $template): array
    {
        if (array_key_exists('checklists', $data)) {
            return $data['checklists'];
        }

        if (! $template) {
            return [];
        }

        return $template->checklists->map(fn ($templateChecklist) => [
            'source_template_checklist_id' => $templateChecklist->id,
            'title' => $templateChecklist->title,
            'instructions' => $templateChecklist->instructions,
            'target_department_id' => $templateChecklist->target_department_id,
            'schedule_type' => $templateChecklist->schedule_type,
            'schedule_config' => $templateChecklist->schedule_config,
            'evidence_min_count' => $templateChecklist->evidence_min_count,
            'allow_upload' => $templateChecklist->allow_upload,
            'allow_camera' => $templateChecklist->allow_camera,
            'works_on_holidays' => $templateChecklist->works_on_holidays,
            'reference_evidence' => $templateChecklist->referenceEvidence,
        ])->all();
    }

    private function createDepartmentRequest(Task $task, TaskChecklist $checklist): DepartmentRequest
    {
        $responseDueAt = null;

        if ($checklist->schedule_type === ScheduleType::Event->value) {
            $hours = $checklist->schedule_config['response_window_hours'] ?? null;
            $responseDueAt = $hours ? now()->addHours($hours) : null;
        }

        $departmentRequest = DepartmentRequest::create([
            'task_id' => $task->id,
            'task_checklist_id' => $checklist->id,
            'owner_department_id' => $task->owner_department_id,
            'target_department_id' => $checklist->target_department_id,
            'status' => DepartmentRequest::STATUS_PENDING,
            'response_due_at' => $responseDueAt,
        ]);

        $this->notifications->notifyManagersOfDepartment(
            $checklist->target_department_id,
            new DepartmentRequestEvent($departmentRequest)
        );

        return $departmentRequest;
    }
}
