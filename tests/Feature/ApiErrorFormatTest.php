<?php

use App\Models\Role;

test('a 403 response never leaks exception, file, or trace details', function () {
    $manager = makeUserWithRoles([Role::MANAGER]);

    $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/users', [
        'name' => 'X',
        'email' => 'x@example.com',
        'password' => 'password123',
    ]);

    $response->assertForbidden()
        ->assertJsonStructure(['message'])
        ->assertJsonMissingPath('exception')
        ->assertJsonMissingPath('file')
        ->assertJsonMissingPath('trace');
});

test('a 404 response never leaks exception, file, or trace details', function () {
    $admin = makeUserWithRoles([Role::ADMIN]);

    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/users/00000000-0000-0000-0000-000000000000');

    $response->assertNotFound()
        ->assertJsonStructure(['message'])
        ->assertJsonMissingPath('exception');
});
