<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Initial role codes per docs/05-DATABASE-SCHEMA.md §2.
     */
    public function run(): void
    {
        $roles = [
            Role::SUPERADMIN => 'Super Admin',
            Role::ADMIN => 'Admin',
            Role::DIRECTOR => 'Director',
            Role::ISO => 'ISO',
            Role::MANAGER => 'Manager',
            Role::PIC => 'PIC',
        ];

        foreach ($roles as $code => $name) {
            Role::query()->firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
