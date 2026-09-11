<?php

use App\Models\ChecklistReferenceEvidence;
use App\Models\Department;
use App\Models\DepartmentRequest;
use App\Models\Holiday;
use App\Models\Role;
use App\Models\TaskScheduleChange;
use App\Models\TaskTemplate;
use App\Models\TemplateReferenceEvidence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('manager can create a manual task with a checklist in their own department', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/tasks', [
        'owner_department_id' => $department->id,
        'title' => 'Toilet Cleaning - Building A',
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addDay()->toIso8601String(),
        'checklists' => [
            [
                'title' => 'Mop floor',
                'schedule_type' => 'daily',
                'schedule_config' => ['start_time' => '08:00', 'end_time' => '09:00'],
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Toilet Cleaning - Building A')
        ->assertJsonPath('data.checklists.0.title', 'Mop floor')
        ->assertJsonPath('data.checklists.0.target_department_id', $department->id);
});

test('manager cannot create a task for a department they do not manage', function () {
    $ownDepartment = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $manager = makeManager($ownDepartment);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/tasks', [
        'owner_department_id' => $otherDepartment->id,
        'title' => 'X',
        'starts_at' => now()->toIso8601String(),
    ]);

    $response->assertForbidden();
});

test('a checklist targeting another department automatically creates a department request', function () {
    $ownerDepartment = Department::factory()->create();
    $targetDepartment = Department::factory()->create();
    $manager = makeManager($ownerDepartment);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/tasks', [
        'owner_department_id' => $ownerDepartment->id,
        'title' => 'Cross Dept Task',
        'starts_at' => now()->toIso8601String(),
        'checklists' => [
            [
                'title' => 'Fix the AC',
                'target_department_id' => $targetDepartment->id,
                'schedule_type' => 'one_time',
                'schedule_config' => ['start_time' => '08:00', 'end_time' => '09:00', 'date' => now()->toDateString()],
            ],
        ],
    ]);

    $response->assertCreated();
    expect(DepartmentRequest::query()
        ->where('target_department_id', $targetDepartment->id)
        ->where('status', DepartmentRequest::STATUS_PENDING)
        ->exists())->toBeTrue();
});

test('a checklist targeting the owner department does not create a department request', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);

    $this->actingAs($manager, 'sanctum')->postJson('/api/v1/tasks', [
        'owner_department_id' => $department->id,
        'title' => 'Own Dept Task',
        'starts_at' => now()->toIso8601String(),
        'checklists' => [
            [
                'title' => 'Sweep floor',
                'target_department_id' => $department->id,
                'schedule_type' => 'daily',
                'schedule_config' => ['start_time' => '08:00', 'end_time' => '09:00'],
            ],
        ],
    ])->assertCreated();

    expect(DepartmentRequest::query()->count())->toBe(0);
});

test('creating a task warns about an overlapping holiday unless the checklist works on holidays', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);
    $holidayDate = now()->addDays(2);
    Holiday::factory()->create(['date' => $holidayDate->toDateString(), 'name' => 'Test Holiday']);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/tasks', [
        'owner_department_id' => $department->id,
        'title' => 'Holiday Overlap Task',
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addDays(5)->toIso8601String(),
        'checklists' => [
            [
                'title' => 'Daily check',
                'schedule_type' => 'daily',
                'schedule_config' => ['start_time' => '08:00', 'end_time' => '09:00'],
                'works_on_holidays' => false,
            ],
        ],
    ]);

    $response->assertCreated();
    expect(collect($response->json('meta.warnings'))->pluck('type'))->toContain('holiday');
});

test('a task snapshot from a template is unaffected by later template edits or deletions', function () {
    Storage::fake('public');

    $admin = makeUserWithRoles([Role::ADMIN]);
    $department = Department::factory()->create();
    $manager = makeManager($department);

    $template = TaskTemplate::factory()->create();
    $templateChecklist = $template->checklists()->create([
        'title' => 'Original Title',
        'schedule_type' => 'daily',
        'schedule_config' => ['start_time' => '08:00', 'end_time' => '09:00'],
    ]);

    $upload = $this->actingAs($admin, 'sanctum')->post("/api/v1/templates/{$template->id}/reference-evidence", [
        'task_template_checklist_id' => $templateChecklist->id,
        'file' => UploadedFile::fake()->image('reference.jpg'),
    ]);
    $upload->assertCreated();

    $taskResponse = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/tasks', [
        'template_id' => $template->id,
        'owner_department_id' => $department->id,
        'starts_at' => now()->toIso8601String(),
    ]);
    $taskResponse->assertCreated();
    $snapshotChecklist = $taskResponse->json('data.checklists.0');
    expect($snapshotChecklist['title'])->toBe('Original Title');
    $snapshotStorageKey = ChecklistReferenceEvidence::first()->storage_key;
    Storage::disk('public')->assertExists($snapshotStorageKey);

    // Now change the template checklist title and delete its reference evidence.
    $templateChecklist->update(['title' => 'Changed Title']);
    $originalEvidence = TemplateReferenceEvidence::first();
    Storage::disk('public')->delete($originalEvidence->storage_key);
    $originalEvidence->delete();

    $taskId = $taskResponse->json('data.id');
    $refetched = $this->actingAs($manager, 'sanctum')->getJson("/api/v1/tasks/{$taskId}");

    $refetched->assertOk()->assertJsonPath('data.checklists.0.title', 'Original Title');
    Storage::disk('public')->assertExists($snapshotStorageKey);
});

test('manager can reschedule a task and the change is recorded', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);

    $create = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/tasks', [
        'owner_department_id' => $department->id,
        'title' => 'Reschedule Me',
        'starts_at' => now()->toIso8601String(),
        'ends_at' => now()->addDay()->toIso8601String(),
    ]);
    $taskId = $create->json('data.id');

    $newStart = now()->addWeek();
    $response = $this->actingAs($manager, 'sanctum')->postJson("/api/v1/tasks/{$taskId}/reschedule", [
        'starts_at' => $newStart->toIso8601String(),
        'ends_at' => $newStart->copy()->addDay()->toIso8601String(),
        'reason' => 'Client requested a later date',
    ]);

    $response->assertOk();
    expect(TaskScheduleChange::where('task_id', $taskId)->count())->toBe(1);
});

test('manager can cancel their own task', function () {
    $department = Department::factory()->create();
    $manager = makeManager($department);

    $create = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/tasks', [
        'owner_department_id' => $department->id,
        'title' => 'Cancel Me',
        'starts_at' => now()->toIso8601String(),
    ]);
    $taskId = $create->json('data.id');

    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/tasks/{$taskId}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

test('a manager from an unrelated department cannot see the task', function () {
    $department = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $manager = makeManager($department);
    $otherManager = makeManager($otherDepartment);

    $create = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/tasks', [
        'owner_department_id' => $department->id,
        'title' => 'Private Task',
        'starts_at' => now()->toIso8601String(),
    ]);
    $taskId = $create->json('data.id');

    $this->actingAs($otherManager, 'sanctum')->getJson("/api/v1/tasks/{$taskId}")->assertForbidden();
});

test('deleting a checklist cancels its pending department request', function () {
    $ownerDepartment = Department::factory()->create();
    $targetDepartment = Department::factory()->create();
    $manager = makeManager($ownerDepartment);

    $create = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/tasks', [
        'owner_department_id' => $ownerDepartment->id,
        'title' => 'Cross Dept Task',
        'starts_at' => now()->toIso8601String(),
        'checklists' => [
            [
                'title' => 'Fix the AC',
                'target_department_id' => $targetDepartment->id,
                'schedule_type' => 'one_time',
                'schedule_config' => ['start_time' => '08:00', 'end_time' => '09:00', 'date' => now()->toDateString()],
            ],
        ],
    ]);
    $taskId = $create->json('data.id');
    $checklistId = $create->json('data.checklists.0.id');

    $this->actingAs($manager, 'sanctum')->deleteJson("/api/v1/tasks/{$taskId}/checklists/{$checklistId}")->assertOk();

    expect(DepartmentRequest::first()->status)->toBe(DepartmentRequest::STATUS_CANCELLED);
});
