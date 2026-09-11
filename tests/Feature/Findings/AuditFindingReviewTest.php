<?php

use App\Models\Finding;
use App\Models\Role;

test('iso can close a manual audit finding after a manager response', function () {
    [$findingId, $iso, $manager] = makeAuditFinding();

    $this->actingAs($manager, 'sanctum')->post("/api/v1/audit-findings/{$findingId}/manager-response", [
        'notes' => 'Fixed it',
    ])->assertOk();

    $response = $this->actingAs($iso, 'sanctum')->postJson("/api/v1/audit-findings/{$findingId}/review", [
        'status' => Finding::STATUS_CLOSED,
        'notes' => 'Confirmed fixed',
    ]);

    $response->assertOk()->assertJsonPath('data.status', Finding::STATUS_CLOSED);
    expect(Finding::find($findingId)->resolved_at)->not->toBeNull();
});

test('iso can reopen a finding with a new deadline', function () {
    [$findingId, $iso] = makeAuditFinding();
    $this->actingAs($iso, 'sanctum')->postJson("/api/v1/audit-findings/{$findingId}/review", [
        'status' => Finding::STATUS_CLOSED,
    ])->assertOk();

    $newDueAt = now()->addDays(5)->toIso8601String();
    $response = $this->actingAs($iso, 'sanctum')->postJson("/api/v1/audit-findings/{$findingId}/review", [
        'status' => Finding::STATUS_OPEN,
        'notes' => 'Not actually fixed, reopening',
        'due_at' => $newDueAt,
    ]);

    $response->assertOk()->assertJsonPath('data.status', Finding::STATUS_OPEN);
    expect(Finding::find($findingId)->resolved_at)->toBeNull();
});

test('iso can mark a finding as info', function () {
    [$findingId, $iso] = makeAuditFinding();

    $this->actingAs($iso, 'sanctum')->postJson("/api/v1/audit-findings/{$findingId}/review", [
        'status' => Finding::STATUS_INFO,
    ])->assertOk()->assertJsonPath('data.status', Finding::STATUS_INFO);
});

test('a non-iso cannot review a manual audit finding', function () {
    [$findingId, , $manager] = makeAuditFinding();

    $this->actingAs($manager, 'sanctum')->postJson("/api/v1/audit-findings/{$findingId}/review", [
        'status' => Finding::STATUS_CLOSED,
    ])->assertForbidden();
});

test('reviewing an automatic finding through the audit-findings route is not found', function () {
    [$finding] = makeFailedFindingSetup();
    $iso = makeUserWithRoles([Role::ISO]);

    $this->actingAs($iso, 'sanctum')->postJson("/api/v1/audit-findings/{$finding->id}/review", [
        'status' => Finding::STATUS_CLOSED,
    ])->assertNotFound();
});
