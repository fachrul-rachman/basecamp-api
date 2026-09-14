<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Support\Facades\Hash;

afterEach(function () {
    putenv('SUPERADMIN_NAME');
    putenv('SUPERADMIN_EMAIL');
    putenv('SUPERADMIN_PASSWORD');
});

test('it throws when superadmin credentials are not configured', function () {
    putenv('SUPERADMIN_EMAIL');
    putenv('SUPERADMIN_PASSWORD');

    expect(fn () => $this->seed(ProductionSeeder::class))->toThrow(RuntimeException::class);
});

test('it seeds roles and exactly one real superadmin from the environment', function () {
    putenv('SUPERADMIN_NAME=Real Owner');
    putenv('SUPERADMIN_EMAIL=owner@real-company.com');
    putenv('SUPERADMIN_PASSWORD=a-strong-password');

    $this->seed(ProductionSeeder::class);

    expect(Role::count())->toBe(6);

    $superadmin = User::where('email', 'owner@real-company.com')->first();
    expect($superadmin)->not->toBeNull();
    expect($superadmin->name)->toBe('Real Owner');
    expect(Hash::check('a-strong-password', $superadmin->password))->toBeTrue();
    expect($superadmin->hasRole(Role::SUPERADMIN))->toBeTrue();
    expect(User::count())->toBe(1);
});

test('it is safe to run twice and never creates a second superadmin', function () {
    putenv('SUPERADMIN_EMAIL=owner@real-company.com');
    putenv('SUPERADMIN_PASSWORD=a-strong-password');

    $this->seed(ProductionSeeder::class);
    $this->seed(ProductionSeeder::class);

    expect(User::count())->toBe(1);
    expect(Role::count())->toBe(6);
});
