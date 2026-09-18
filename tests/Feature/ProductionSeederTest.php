<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ProductionSeeder;
use Illuminate\Support\Facades\Hash;

test('it throws when superadmin credentials are not configured', function () {
    config(['superadmin.email' => null, 'superadmin.password' => null]);

    expect(fn () => $this->seed(ProductionSeeder::class))->toThrow(RuntimeException::class);
});

test('it seeds roles and exactly one real superadmin from the environment', function () {
    config([
        'superadmin.name' => 'Real Owner',
        'superadmin.email' => 'owner@real-company.com',
        'superadmin.password' => 'a-strong-password',
    ]);

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
    config([
        'superadmin.email' => 'owner@real-company.com',
        'superadmin.password' => 'a-strong-password',
    ]);

    $this->seed(ProductionSeeder::class);
    $this->seed(ProductionSeeder::class);

    expect(User::count())->toBe(1);
    expect(Role::count())->toBe(6);
});
