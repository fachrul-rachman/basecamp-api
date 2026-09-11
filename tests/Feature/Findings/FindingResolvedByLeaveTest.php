<?php

use App\Models\Finding;
use App\Models\SlaInstance;
use App\Services\FindingService;

test('resolveDueToApprovedLeave resolves the finding and completes its running SLA', function () {
    [$finding] = makeFailedFindingSetup();

    expect($finding->status)->toBe(Finding::STATUS_WAITING_MANAGER_ACTION);
    expect($finding->slaInstances()->where('status', SlaInstance::STATUS_RUNNING)->exists())->toBeTrue();

    $resolved = app(FindingService::class)->resolveDueToApprovedLeave($finding);

    expect($resolved->status)->toBe(Finding::STATUS_RESOLVED);
    expect($resolved->resolution_type)->toBe('leave_approved');
    expect($resolved->resolved_at)->not->toBeNull();
    expect($resolved->slaInstances->every(fn ($i) => $i->status === SlaInstance::STATUS_COMPLETED))->toBeTrue();
});
