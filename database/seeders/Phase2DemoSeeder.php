<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\SlaSetting;
use App\Models\User;
use App\Models\WorkingCalendar;
use Illuminate\Database\Seeder;

/**
 * Idempotent, additive seeding for Phase 2 demo data on a database that
 * already has Phase 1 data (safe to run more than once; does not touch
 * or duplicate existing Phase 1 rows).
 */
class Phase2DemoSeeder extends Seeder
{
    public function run(): void
    {
        $officeCalendar = WorkingCalendar::query()->firstOrCreate(
            ['name' => 'Office Hours (Mon-Fri 08:00-17:00)'],
            ['timezone' => 'Asia/Jakarta', 'is_active' => true]
        );

        if ($officeCalendar->hours()->count() === 0) {
            foreach (range(0, 6) as $weekday) {
                $isWorkingDay = $weekday >= 1 && $weekday <= 5;

                $officeCalendar->hours()->create([
                    'weekday' => $weekday,
                    'start_time' => $isWorkingDay ? '08:00' : null,
                    'end_time' => $isWorkingDay ? '17:00' : null,
                    'is_working_day' => $isWorkingDay,
                ]);
            }
        }

        $managerPic = User::query()->where('email', 'manager.pic@example.com')->first();
        $managerPic?->update(['working_calendar_id' => $officeCalendar->id]);

        SlaSetting::query()->firstOrCreate(
            ['scope_type' => SlaSetting::SCOPE_GLOBAL, 'scope_id' => null],
            ['minutes' => 240]
        );
        SlaSetting::query()->firstOrCreate(
            ['scope_type' => SlaSetting::SCOPE_ISO, 'scope_id' => null],
            ['minutes' => 480]
        );

        $iso = User::query()->where('email', 'iso@example.com')->first();

        if (! $iso) {
            $iso = User::factory()->create(['name' => 'ISO Demo', 'email' => 'iso@example.com']);
            $iso->roles()->attach(Role::where('code', Role::ISO)->first());
        }
    }
}
