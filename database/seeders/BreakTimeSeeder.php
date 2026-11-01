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
            if ($attendance->clock_in === null) {
                continue;
            }

            if ((int) $attendance->user_id === 1) {
                BreakTime::factory()->create([
                    'attendance_id' => $attendance->id,
                    'break_in' => '12:00:00',
                    'break_out' => '13:00:00',
                ]);

                continue;
            }

            BreakTime::factory()->create([
                'attendance_id' => $attendance->id,
            ]);
        }
    }
}
