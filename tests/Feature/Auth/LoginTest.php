<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

test('user can login with valid credentials and receive a token', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'email']]]);
});

test('login fails with an invalid password', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422);
});

test('inactive user cannot login', function () {
    $user = User::factory()->create([
        'password' => bcrypt('secret123'),
        'is_active' => false,
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ]);

    $response->assertStatus(422);
});

test('me endpoint requires authentication', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

test('me endpoint returns the authenticated user with roles and departments', function () {
    $user = makeUserWithRoles(['manager', 'pic']);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/me');

    $response->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonCount(2, 'data.roles');
});

test('logout revokes the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/logout');

    $response->assertOk();
    expect($user->tokens()->count())->toBe(0);
});

test('login is rate limited after repeated attempts', function () {
    $user = User::factory()->create(['password' => bcrypt('secret123')]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertStatus(422);
    }

    $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'wrong'])
        ->assertStatus(429);
});

test('an expired token is rejected', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $accessToken = PersonalAccessToken::findToken(explode('|', $token, 2)[1]);
    $accessToken->forceFill(['created_at' => Carbon::now()->subMinutes((int) config('sanctum.expiration') + 1)])->save();

    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/me')->assertUnauthorized();
});
