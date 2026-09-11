<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\SlaSetting;
use App\Services\SlaSettingService;

beforeEach(function () {
    $this->service = new SlaSettingService;
});

test('falls back to global sla when no overrides exist', function () {
    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_GLOBAL, 'scope_id' => null, 'minutes' => 240]);

    $manager = makeUserWithRoles([Role::MANAGER]);

    expect($this->service->resolveManagerSla($manager))->toBe(240);
});

test('department override takes precedence over global', function () {
    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_GLOBAL, 'scope_id' => null, 'minutes' => 240]);

    $department = Department::factory()->create();
    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_DEPARTMENT, 'scope_id' => $department->id, 'minutes' => 120]);

    $manager = makeUserWithRoles([Role::MANAGER]);
    $manager->departments()->attach($department->id, ['is_primary' => true]);
    $manager->load('departments');

    expect($this->service->resolveManagerSla($manager))->toBe(120);
});

test('manager override takes precedence over department and global', function () {
    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_GLOBAL, 'scope_id' => null, 'minutes' => 240]);

    $department = Department::factory()->create();
    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_DEPARTMENT, 'scope_id' => $department->id, 'minutes' => 120]);

    $manager = makeUserWithRoles([Role::MANAGER]);
    $manager->departments()->attach($department->id, ['is_primary' => true]);
    $manager->load('departments');

    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_MANAGER, 'scope_id' => $manager->id, 'minutes' => 60]);

    expect($this->service->resolveManagerSla($manager))->toBe(60);
});

test('resolves the single iso sla value', function () {
    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_ISO, 'scope_id' => null, 'minutes' => 480]);

    expect($this->service->resolveIsoSla())->toBe(480);
});

test('set with null minutes deactivates an existing override', function () {
    $department = Department::factory()->create();
    $this->service->set(SlaSetting::SCOPE_DEPARTMENT, $department->id, 120);

    $this->service->set(SlaSetting::SCOPE_DEPARTMENT, $department->id, null);

    expect(
        SlaSetting::where('scope_type', SlaSetting::SCOPE_DEPARTMENT)
            ->where('scope_id', $department->id)
            ->where('is_active', true)
            ->exists()
    )->toBeFalse();
});
