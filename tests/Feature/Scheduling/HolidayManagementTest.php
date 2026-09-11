<?php

use App\Models\Holiday;
use App\Models\Role;

test('admin can create, update, and delete a holiday', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);

    $create = $this->actingAs($admin, 'sanctum')->postJson('/api/v1/holidays', [
        'date' => '2026-12-25',
        'name' => 'Christmas',
        'scope' => Holiday::SCOPE_NATIONAL,
    ]);
    $create->assertCreated();
    $id = $create->json('data.id');

    $this->actingAs($admin, 'sanctum')->patchJson("/api/v1/holidays/{$id}", ['name' => 'Christmas Day'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Christmas Day');

    $this->actingAs($admin, 'sanctum')->deleteJson("/api/v1/holidays/{$id}")->assertOk();

    expect(Holiday::find($id))->toBeNull();
});

test('any authenticated role can list holidays', function () {
    Holiday::factory()->count(2)->create();
    $pic = makeUserWithRoles([Role::PIC]);

    $response = $this->actingAs($pic, 'sanctum')->getJson('/api/v1/holidays');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

test('non-admin cannot create a holiday', function () {
    $manager = makeUserWithRoles([Role::MANAGER]);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/holidays', [
        'date' => '2026-01-01',
        'name' => 'New Year',
        'scope' => Holiday::SCOPE_NATIONAL,
    ]);

    $response->assertForbidden();
});
