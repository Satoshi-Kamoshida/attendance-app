<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use Illuminate\Database\Seeder;

class AttendanceCorrectionRequestSeeder extends Seeder
{
    public function run(): void
    {
        $attendances = Attendance::whereHas('breakTimes')->take(3)->get();

        $withoutBreakTime = Attendance::whereDoesntHave('breakTimes')->first();
        $actions = [
            'delete',
            'only_clock_in',
            'only_clock_out',
            'clock_in_and_out',
        ];

        foreach ($attendances as $i => $attendance) {
            if ($actions[$i] === 'delete') {
                AttendanceCorrectionRequest::factory()->delete()->create([
                    'attendance_id' => $attendance->id,
                ]);
            } elseif ($actions[$i] === 'only_clock_in') {
                AttendanceCorrectionRequest::factory()->only_clock_in()->create([
                    'attendance_id' => $attendance->id,
                ]);
            } elseif ($actions[$i] === 'only_clock_out') {
                AttendanceCorrectionRequest::factory()->only_clock_out()->create([
                    'attendance_id' => $attendance->id,
                ]);
            }
        }

        if ($withoutBreakTime) {
            AttendanceCorrectionRequest::factory()
                ->clock_in_and_out()
                ->create([
                    'attendance_id' => $withoutBreakTime->id,
                ]);
        }
    }
}
