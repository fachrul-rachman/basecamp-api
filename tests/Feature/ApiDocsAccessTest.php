<?php

use App\Models\Role;
use Laravel\Sanctum\PersonalAccessToken;

test('a superadmin can view the api docs with a bearer token', function () {
    // Scramble builds the full OpenAPI document on every real render of
    // /docs/api, which needs more than PHP's default 128M CLI memory_limit
    // once it runs alongside the rest of the suite in one process.
    ini_set('memory_limit', '512M');

    $superadmin = makeUserWithRoles([Role::SUPERADMIN]);
    $token = $superadmin->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")->get('/docs/api')->assertOk();
});

test('a superadmin can view the api docs with a token query parameter', function () {
    ini_set('memory_limit', '512M');

    $superadmin = makeUserWithRoles([Role::SUPERADMIN]);
    $token = $superadmin->createToken('test')->plainTextToken;

    $this->get("/docs/api?token={$token}")->assertOk();
});

test('a non-superadmin authenticated user cannot view the api docs', function () {
    $manager = makeUserWithRoles([Role::MANAGER]);
    $token = $manager->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")->get('/docs/api')->assertForbidden();
});

test('an unauthenticated request cannot view the api docs', function () {
    $this->get('/docs/api')->assertForbidden();
});

test('an expired superadmin token cannot view the api docs', function () {
    $superadmin = makeUserWithRoles([Role::SUPERADMIN]);
    $token = $superadmin->createToken('test')->plainTextToken;

    $accessToken = PersonalAccessToken::findToken(explode('|', $token, 2)[1]);
    $accessToken->forceFill(['created_at' => now()->subMinutes((int) config('sanctum.expiration') + 1)])->save();

    $this->get("/docs/api?token={$token}")->assertForbidden();
});
