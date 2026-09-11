<?php

use App\Models\Finding;
use App\Models\Role;
use App\Models\WorkItem;

test('iso can accept an explanation, resolving the finding without erasing the compliance fact', function () {
    [$finding, $manager] = makeFailedFindingSetup();
    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/findings/{$finding->id}/explanation", ['notes' => 'x'])
        ->assertOk();

    $iso = makeUserWithRoles([Role::ISO]);
    $response = $this->actingAs($iso, 'sanctum')->postJson("/api/v1/findings/{$finding->id}/iso-review", [
        'decision' => 'accept',
        'notes' => 'Reasonable explanation',
    ]);

    $response->assertOk()->assertJsonPath('data.status', Finding::STATUS_RESOLVED);
    $workItem = WorkItem::find($finding->fresh()->work_item_id);
    expect($workItem->compliance_status)->toBe(WorkItem::COMPLIANCE_FAILED);
});

test('iso rejecting an explanation sends it back to the manager', function () {
    [$finding, $manager] = makeFailedFindingSetup();
    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/findings/{$finding->id}/explanation", ['notes' => 'x'])
        ->assertOk();

    $iso = makeUserWithRoles([Role::ISO]);
    $this->actingAs($iso, 'sanctum')->postJson("/api/v1/findings/{$finding->id}/iso-review", [
        'decision' => 'reject',
    ])->assertOk()->assertJsonPath('data.status', Finding::STATUS_WAITING_MANAGER_ACTION);
});

test('a non-iso cannot review a finding', function () {
    [$finding, $manager] = makeFailedFindingSetup();
    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/findings/{$finding->id}/explanation", ['notes' => 'x'])
        ->assertOk();

    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/findings/{$finding->id}/iso-review", [
        'decision' => 'accept',
    ])->assertForbidden();
});

test('cannot review a finding that is not awaiting iso review', function () {
    [$finding] = makeFailedFindingSetup();
    $iso = makeUserWithRoles([Role::ISO]);

    $this->actingAs($iso, 'sanctum')->postJson("/api/v1/findings/{$finding->id}/iso-review", [
        'decision' => 'accept',
    ])->assertStatus(422);
});
