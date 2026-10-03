<?php

namespace Database\Seeders;

use App\Models\Attendance;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $period = CarbonPeriod::create('2026-09-01', '2026-11-30');

        foreach ($period as $date) {
            Attendance::factory()->create([
                'user_id' => 1,
                'date' => $date->format('Y-m-d'),
            ]);

            Attendance::factory()->create([
                'user_id' => 2,
                'date' => $date->format('Y-m-d'),
            ]);
        }
    }
}
