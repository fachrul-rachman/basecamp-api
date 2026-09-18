<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds ONLY what a real deployment needs to become usable: reference
 * roles and exactly one real SuperAdmin account, read from the
 * environment (never hardcoded) — no demo departments/tasks/users. Every
 * other real user/department/task is created afterward through the API
 * itself, by that SuperAdmin. Safe to run more than once (idempotent).
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        if (User::query()->whereHas('roles', fn ($q) => $q->where('code', Role::SUPERADMIN))->exists()) {
            $this->command?->info('A SuperAdmin already exists — skipping.');

            return;
        }

        $email = config('superadmin.email');
        $password = config('superadmin.password');

        if (! $email || ! $password) {
            throw new \RuntimeException(
                'Set SUPERADMIN_EMAIL and SUPERADMIN_PASSWORD in .env before running this seeder.'
            );
        }

        $superadmin = User::create([
            'name' => config('superadmin.name'),
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $superadmin->roles()->attach(Role::where('code', Role::SUPERADMIN)->first());

        $this->command?->info("SuperAdmin created: {$email}");
    }
}
