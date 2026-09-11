<?php

use App\Models\Department;
use App\Models\DepartmentRequest;
use App\Models\Evidence;
use App\Models\Finding;
use App\Models\Role;
use App\Models\SlaInstance;
use App\Models\SlaSetting;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateChecklist;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function makeUserWithRoles(array $roleCodes, array $attributes = []): User
{
    $user = User::factory()->create($attributes);

    $roles = collect($roleCodes)->map(
        fn ($code) => Role::firstOrCreate(['code' => $code], ['name' => ucfirst($code)])
    );

    $user->roles()->attach($roles->pluck('id'));

    return $user->fresh(['roles', 'departments']);
}

function makeTemplateChecklist(TaskTemplate $template, string $title = 'Mop floor'): TaskTemplateChecklist
{
    return $template->checklists()->create([
        'title' => $title,
        'schedule_type' => 'daily',
        'schedule_config' => ['start_time' => '08:00', 'end_time' => '09:00'],
    ]);
}

function fakeHolidayApiPage(array $entries, int $page = 1, int $totalPage = 1): array
{
    return [
        'is_success' => true,
        'message' => 'Success',
        'data' => $entries,
        'paging' => ['page' => $page, 'size' => 100, 'total_item' => count($entries), 'total_page' => $totalPage],
    ];
}

function fakeHolidayEntry(array $overrides = []): array
{
    return array_merge([
        'id' => 1166,
        'date' => '2026-01-01',
        'date_formatted' => '01 Januari 2026',
        'day_of_week' => 'Kamis',
        'name' => "New Year's Day",
        'type' => 'Public Holiday',
        'year' => 2026,
        'is_today' => false,
        'is_upcoming' => false,
        'is_holiday' => true,
        'is_joint_holiday' => false,
        'is_observance' => false,
    ], $overrides);
}

function makeManager(Department $department, bool $primary = true): User
{
    $manager = makeUserWithRoles([Role::MANAGER]);
    $manager->departments()->attach($department->id, ['is_primary' => $primary]);

    return $manager->fresh(['roles', 'departments']);
}

/**
 * @return array{0: User, 1: User, 2: Department} [manager, pic, department]
 */
function makePicInManagedDepartment(): array
{
    $department = Department::factory()->create();
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);
    $pic->load('departments');

    return [$manager, $pic, $department];
}

/**
 * @return array{request: DepartmentRequest, ownerDepartment: Department, targetDepartment: Department, ownerManager: User, targetManager: User}
 */
function createCrossDepartmentRequest(): array
{
    $ownerDepartment = Department::factory()->create();
    $targetDepartment = Department::factory()->create();
    $ownerManager = makeManager($ownerDepartment);
    $targetManager = makeManager($targetDepartment);

    test()->actingAs($ownerManager, 'sanctum')->postJson('/api/v1/tasks', [
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

    return [
        'request' => DepartmentRequest::first(),
        'ownerDepartment' => $ownerDepartment,
        'targetDepartment' => $targetDepartment,
        'ownerManager' => $ownerManager,
        'targetManager' => $targetManager,
    ];
}

/**
 * @return array{0: Task, 1: TaskChecklist, 2: Department}
 */
function makeTaskWithChecklist(array $checklistOverrides = [], array $taskOverrides = []): array
{
    $department = $taskOverrides['department'] ?? Department::factory()->create();

    $task = Task::factory()->create(array_merge([
        'owner_department_id' => $department->id,
        'starts_at' => now()->startOfDay(),
        'ends_at' => now()->addDays(60),
    ], $taskOverrides['attributes'] ?? []));

    $checklist = $task->checklists()->create(array_merge([
        'title' => 'Test Checklist',
        'target_department_id' => $department->id,
        'schedule_type' => 'daily',
        'schedule_config' => ['start_time' => '08:00', 'end_time' => '17:00'],
        'evidence_min_count' => 1,
    ], $checklistOverrides));

    return [$task, $checklist, $department];
}

/**
 * @return array{0: WorkItem, 1: User}
 */
function makeAvailableWorkItem(array $checklistOverrides = []): array
{
    [, $checklist] = makeTaskWithChecklist($checklistOverrides);
    $pic = makeUserWithRoles([Role::PIC]);
    $item = WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->toDateString(),
        'available_at' => now()->subHour(),
        'deadline_at' => now()->addHours(2),
        'failure_at' => now()->addHours(3),
        'rule_snapshot' => [
            'allow_upload' => $checklist->allow_upload,
            'allow_camera' => $checklist->allow_camera,
        ],
    ]);

    return [$item, $pic];
}

/**
 * Creates a failed Work Item, runs the evaluation job to produce its
 * automatic Finding + Manager SLA instance, and returns the cast of
 * characters needed by Finding-response tests.
 *
 * @return array{0: Finding, 1: User, 2: User, 3: Department}
 */
function makeFailedFindingSetup(): array
{
    [, $checklist, $department] = makeTaskWithChecklist();
    $manager = makeManager($department);
    $pic = makeUserWithRoles([Role::PIC]);
    $pic->departments()->attach($department->id);

    SlaSetting::query()->firstOrCreate(
        ['scope_type' => SlaSetting::SCOPE_GLOBAL, 'scope_id' => null],
        ['minutes' => 240]
    );

    WorkItem::create([
        'task_id' => $checklist->task_id,
        'task_checklist_id' => $checklist->id,
        'responsible_department_id' => $checklist->target_department_id,
        'assignee_id' => $pic->id,
        'operational_date' => now()->subDay()->toDateString(),
        'available_at' => now()->subDay(),
        'deadline_at' => now()->subHours(5),
        'failure_at' => now()->subHour(),
    ]);

    test()->artisan('work-items:evaluate');

    return [Finding::first(), $manager, $pic, $department];
}

/**
 * @return array{0: Evidence, 1: User, 2: Department} [evidence, manager, department]
 */
function makeEvidenceWithReferenceChecklist(): array
{
    $department = Department::factory()->create();
    $manager = makeManager($department);
    [$item, $pic] = makeAvailableWorkItem(['target_department_id' => $department->id]);
    $item->update(['responsible_department_id' => $department->id]);

    $checklist = $item->taskChecklist;
    $checklist->referenceEvidence()->create([
        'storage_key' => 'checklist-reference-evidence/ref.jpg',
        'metadata' => ['original_name' => 'ref.jpg', 'mime_type' => 'image/jpeg', 'size' => 100],
    ]);

    Storage::disk('public')->put('checklist-reference-evidence/ref.jpg', 'fake-reference-bytes');
    Storage::disk('public')->put('work-item-evidence/new.jpg', 'fake-evidence-bytes');

    $submission = $item->submission()->firstOrCreate([], ['user_id' => $pic->id]);
    $evidence = $submission->evidence()->create([
        'storage_key' => 'work-item-evidence/new.jpg',
        'source_type' => 'upload',
        'uploaded_at' => now(),
        'metadata' => ['original_name' => 'new.jpg', 'mime_type' => 'image/jpeg', 'size' => 100],
    ]);

    return [$evidence, $manager, $department];
}

/**
 * @return array{0: string, 1: User, 2: User} [findingId, iso, manager]
 */
function makeAuditFinding(?string $status = null): array
{
    $iso = makeUserWithRoles([Role::ISO]);
    $department = Department::factory()->create();
    $manager = makeManager($department);

    $create = test()->actingAs($iso, 'sanctum')->postJson('/api/v1/audit-findings', [
        'title' => 'Safety walkthrough finding',
        'target_department_id' => $department->id,
        'target_manager_id' => $manager->id,
        'status' => $status ?? Finding::STATUS_OPEN,
        'due_at' => now()->addDays(3)->toIso8601String(),
    ]);

    return [$create->json('data.id'), $iso, $manager];
}

/**
 * A bare automatic Finding for scoring tests — deliberately NOT wired
 * through the full evaluate/explain/ISO-review flow (those are already
 * covered by their own tests); this only needs the exact fields
 * ComplianceScoreService reads.
 */
function makeAutomaticFinding(User $pic, string $findingType, ?string $resolutionType = null, ?Carbon $openedAt = null): Finding
{
    return Finding::create([
        'source_type' => Finding::SOURCE_AUTOMATIC,
        'finding_type' => $findingType,
        'target_user_id' => $pic->id,
        'target_department_id' => Department::factory()->create()->id,
        'title' => 'Test finding',
        'status' => Finding::STATUS_WAITING_MANAGER_ACTION,
        'resolution_type' => $resolutionType,
        'opened_at' => $openedAt ?? now(),
    ]);
}

/**
 * A bare Manager SlaInstance for scoring tests, same reasoning as
 * makeAutomaticFinding() above — attaches to a real Finding since
 * sla_instances.finding_id is a required foreign key.
 */
function makeManagerSlaInstance(User $manager, Finding $finding, string $status, ?Carbon $breachedAt = null): SlaInstance
{
    return SlaInstance::create([
        'finding_id' => $finding->id,
        'responsible_user_id' => $manager->id,
        'sla_type' => SlaInstance::TYPE_MANAGER,
        'effective_minutes' => 240,
        'started_at' => now(),
        'due_at' => now()->addHours(4),
        'status' => $status,
        'breached_at' => $status === SlaInstance::STATUS_BREACHED ? ($breachedAt ?? now()) : null,
    ]);
}
