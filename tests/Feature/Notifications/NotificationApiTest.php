<?php

use App\Models\Role;
use App\Notifications\WorkItemEvent;

test('a user only sees their own notifications', function () {
    [$item, $pic] = makeAvailableWorkItem();
    $otherPic = makeUserWithRoles([Role::PIC]);

    $pic->notify(new WorkItemEvent($item, WorkItemEvent::ASSIGNED));
    $otherPic->notify(new WorkItemEvent($item, WorkItemEvent::ASSIGNED));

    $response = $this->actingAs($pic, 'sanctum')->getJson('/api/v1/notifications');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

test('a user can mark one notification as read', function () {
    [$item, $pic] = makeAvailableWorkItem();
    $pic->notify(new WorkItemEvent($item, WorkItemEvent::ASSIGNED));
    $notificationId = $pic->notifications()->first()->id;

    $this->actingAs($pic, 'sanctum')->postJson("/api/v1/notifications/{$notificationId}/read")->assertOk();

    expect($pic->notifications()->first()->read_at)->not->toBeNull();
});

test('a user cannot mark another users notification as read', function () {
    [$item, $pic] = makeAvailableWorkItem();
    $otherPic = makeUserWithRoles([Role::PIC]);
    $pic->notify(new WorkItemEvent($item, WorkItemEvent::ASSIGNED));
    $notificationId = $pic->notifications()->first()->id;

    $this->actingAs($otherPic, 'sanctum')->postJson("/api/v1/notifications/{$notificationId}/read")->assertNotFound();
});

test('a user can mark all notifications as read at once', function () {
    [$item, $pic] = makeAvailableWorkItem();
    $pic->notify(new WorkItemEvent($item, WorkItemEvent::ASSIGNED));
    $pic->notify(new WorkItemEvent($item, WorkItemEvent::REASSIGNED));

    $this->actingAs($pic, 'sanctum')->postJson('/api/v1/notifications/read-all')->assertOk();

    expect($pic->unreadNotifications()->count())->toBe(0);
});
