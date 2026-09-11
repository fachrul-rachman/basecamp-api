<?php

use App\Models\Finding;
use App\Models\Role;
use App\Models\SlaInstance;
use App\Models\SlaSetting;
use App\Services\FindingService;

test('a breached manager sla escalates the finding to iso and records the breach independently', function () {
    [$finding] = makeFailedFindingSetup();
    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_ISO, 'scope_id' => null, 'minutes' => 480]);
    $iso = makeUserWithRoles([Role::ISO]);

    // Force the running Manager SLA instance to already be overdue.
    SlaInstance::first()->update(['due_at' => now()->subMinute()]);

    $this->artisan('findings:evaluate-sla');

    expect($finding->fresh()->status)->toBe(Finding::STATUS_ESCALATED);

    $instances = SlaInstance::orderBy('created_at')->get();
    expect($instances)->toHaveCount(2);
    expect($instances[0]->sla_type)->toBe(SlaInstance::TYPE_MANAGER);
    expect($instances[0]->status)->toBe(SlaInstance::STATUS_BREACHED);
    expect($instances[0]->breached_at)->not->toBeNull();
    expect($instances[1]->sla_type)->toBe(SlaInstance::TYPE_ISO);
    expect($instances[1]->responsible_user_id)->toBe($iso->id);
    expect($instances[1]->status)->toBe(SlaInstance::STATUS_RUNNING);
});

test('a manager sla that has not expired yet does not escalate', function () {
    [$finding] = makeFailedFindingSetup();

    $this->artisan('findings:evaluate-sla');

    expect($finding->fresh()->status)->toBe(Finding::STATUS_WAITING_MANAGER_ACTION);
    expect(SlaInstance::count())->toBe(1);
});

test('a failure while starting the iso sla rolls back the manager sla breach', function () {
    [$finding] = makeFailedFindingSetup();
    SlaSetting::create(['scope_type' => SlaSetting::SCOPE_ISO, 'scope_id' => null, 'minutes' => 480]);
    makeUserWithRoles([Role::ISO]);

    $instance = SlaInstance::first();
    $instance->update(['due_at' => now()->subMinute()]);

    $this->partialMock(FindingService::class, function ($mock) {
        $mock->shouldReceive('startIsoSla')->andThrow(new RuntimeException('boom'));
    });

    expect(fn () => $this->artisan('findings:evaluate-sla'))->toThrow(RuntimeException::class);

    expect($finding->fresh()->status)->toBe(Finding::STATUS_WAITING_MANAGER_ACTION);
    expect($instance->fresh()->status)->toBe(SlaInstance::STATUS_RUNNING);
    expect(SlaInstance::count())->toBe(1);
});

test('the findings sla endpoint reports remaining minutes for a running instance', function () {
    [$finding, $manager] = makeFailedFindingSetup();

    $response = $this->actingAs($manager, 'sanctum')->getJson("/api/v1/findings/{$finding->id}/sla");

    $response->assertOk()
        ->assertJsonPath('data.status', SlaInstance::STATUS_RUNNING)
        ->assertJsonPath('data.effective_minutes', 240)
        ->assertJsonPath('data.is_breached', false);
    expect($response->json('data.remaining_work_minutes'))->toBeInt();
});
