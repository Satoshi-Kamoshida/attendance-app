<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\BreakTime;
use Illuminate\Database\Seeder;

class BreakTimeSeeder extends Seeder
{
    public function run(): void
    {
        $attendances = Attendance::all();

        foreach ($attendances as $attendance) {
            if ($attendance->clock_in !== null) {
                BreakTime::factory()->create([
                    'attendance_id' => $attendance->id,
                ]);
            }
        }
    }
}
