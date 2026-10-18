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

            if ($attendance->user_id === 1) {
                // user1：すべての対象勤怠に固定休憩を設定する。
                BreakTime::factory()->create([
                    'attendance_id' => $attendance->id,
                    'break_in' => '12:00:00',
                    'break_out' => '13:00:00',
                ]);

                continue;
            }

            // user2：従来のランダムな休憩データを維持する。
            BreakTime::factory()->create([
                'attendance_id' => $attendance->id,
            ]);
        }
    }
}
