<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\SlaSetting;
use App\Models\User;
use App\Models\WorkingCalendar;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with reference data and a demo
     * dataset so the API can be exercised manually right after install.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $department = Department::factory()->create([
            'code' => 'OPS',
            'name' => 'Operations',
        ]);

        $superadmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
        ]);
        $superadmin->roles()->attach(Role::where('code', Role::SUPERADMIN)->first());

        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);
        $admin->roles()->attach(Role::where('code', Role::ADMIN)->first());

        $iso = User::factory()->create([
            'name' => 'ISO Demo',
            'email' => 'iso@example.com',
        ]);
        $iso->roles()->attach(Role::where('code', Role::ISO)->first());

        // Demonstrates the Manager + PIC dual-role scenario required by
        // Phase 1 acceptance ("multi-role user supported").
        $managerPic = User::factory()->create([
            'name' => 'Manager Pic Demo',
            'email' => 'manager.pic@example.com',
        ]);
        $managerPic->roles()->attach(Role::whereIn('code', [Role::MANAGER, Role::PIC])->pluck('id'));
        $managerPic->departments()->attach($department->id, ['is_primary' => true]);

        $officeCalendar = WorkingCalendar::factory()->withOfficeHours()->create([
            'name' => 'Office Hours (Mon-Fri 08:00-17:00)',
        ]);
        $managerPic->update(['working_calendar_id' => $officeCalendar->id]);

        // Placeholder defaults so SlaSettingService always has a base value
        // to resolve; Admin/ISO can change these via the API at any time.
        SlaSetting::create([
            'scope_type' => SlaSetting::SCOPE_GLOBAL,
            'scope_id' => null,
            'minutes' => 240, // 4 working hours
        ]);
        SlaSetting::create([
            'scope_type' => SlaSetting::SCOPE_ISO,
            'scope_id' => null,
            'minutes' => 480, // 8 working hours
        ]);
    }
}
