<?php

namespace App\Services;

use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TaskTemplateService
{
    public function __construct(private AuditLogService $auditLog) {}

    public function create(User $actor, array $data): TaskTemplate
    {
        return DB::transaction(function () use ($actor, $data) {
            $template = TaskTemplate::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => $actor->id,
            ]);

            if (array_key_exists('department_ids', $data)) {
                $template->departments()->sync($data['department_ids'] ?? []);
            }

            foreach ($data['checklists'] ?? [] as $index => $checklist) {
                $template->checklists()->create($this->checklistAttributes($checklist, $index));
            }

            $this->auditLog->record($actor, 'task_template.created', 'TaskTemplate', $template->id);

            return $template->load(['departments', 'checklists.referenceEvidence']);
        });
    }

    public function update(User $actor, TaskTemplate $template, array $data): TaskTemplate
    {
        return DB::transaction(function () use ($actor, $template, $data) {
            $template->fill(array_intersect_key($data, array_flip(['name', 'description', 'is_active'])))->save();

            if (array_key_exists('department_ids', $data)) {
                $template->departments()->sync($data['department_ids'] ?? []);
            }

            // Templates are not operational history, so a full
            // replace-by-payload sync is safe here (see docs/03-DOMAIN-MODEL.md
            // §4 and the Phase 3 acceptance criteria).
            if (array_key_exists('checklists', $data)) {
                $this->syncChecklists($template, $data['checklists'] ?? []);
            }

            $this->auditLog->record($actor, 'task_template.updated', 'TaskTemplate', $template->id);

            return $template->load(['departments', 'checklists.referenceEvidence']);
        });
    }

    public function deactivate(User $actor, TaskTemplate $template): TaskTemplate
    {
        $template->update(['is_active' => false]);
        $this->auditLog->record($actor, 'task_template.deactivated', 'TaskTemplate', $template->id);

        return $template;
    }

    private function syncChecklists(TaskTemplate $template, array $checklists): void
    {
        $keptIds = [];

        foreach ($checklists as $index => $checklist) {
            $attributes = $this->checklistAttributes($checklist, $index);

            if (! empty($checklist['id'])) {
                $template->checklists()->whereKey($checklist['id'])->update($attributes);
                $keptIds[] = $checklist['id'];
            } else {
                $keptIds[] = $template->checklists()->create($attributes)->id;
            }
        }

        $template->checklists()->whereNotIn('id', $keptIds)->delete();
    }

    private function checklistAttributes(array $checklist, int $index): array
    {
        return [
            'title' => $checklist['title'],
            'instructions' => $checklist['instructions'] ?? null,
            'target_department_id' => $checklist['target_department_id'] ?? null,
            'schedule_type' => $checklist['schedule_type'],
            'schedule_config' => $checklist['schedule_config'] ?? [],
            'evidence_min_count' => $checklist['evidence_min_count'] ?? 0,
            'allow_upload' => $checklist['allow_upload'] ?? true,
            'allow_camera' => $checklist['allow_camera'] ?? true,
            'works_on_holidays' => $checklist['works_on_holidays'] ?? false,
            'sort_order' => $checklist['sort_order'] ?? $index,
        ];
    }
}
