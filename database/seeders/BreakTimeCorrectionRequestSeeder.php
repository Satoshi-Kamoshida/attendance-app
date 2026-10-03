<?php

namespace Database\Seeders;

use App\Models\AttendanceCorrectionRequest;
use App\Models\BreakTimeCorrectionRequest;
use Illuminate\Database\Seeder;

class BreakTimeCorrectionRequestSeeder extends Seeder
{
    public function run(): void
    {
        $attendanceCorrectionRequests = AttendanceCorrectionRequest::whereHas('attendance.breakTimes')->take(3)->get();

        $withoutBreakTime = AttendanceCorrectionRequest::whereDoesntHave('attendance.breakTimes')->first();

        $actions = [
            'delete',
            'only_break_in',
            'only_break_out',
            'break_in_and_out',
        ];

        foreach ($attendanceCorrectionRequests as $i => $attendanceCorrectionRequest) {

            $attendance = $attendanceCorrectionRequest->attendance;

            if ($attendance && $attendance->breakTimes->isNotEmpty()) {
                $breakTime = $attendance->breakTimes->first();
                if ($actions[$i] === 'delete') {
                    BreakTimeCorrectionRequest::factory()->delete($breakTime)->create([
                        'attendance_correction_request_id' => $attendanceCorrectionRequest->id,
                    ]);
                } elseif ($actions[$i] === 'only_break_in') {
                    BreakTimeCorrectionRequest::factory()->only_break_in($breakTime)->create([
                        'attendance_correction_request_id' => $attendanceCorrectionRequest->id,
                    ]);
                } elseif ($actions[$i] === 'only_break_out') {
                    BreakTimeCorrectionRequest::factory()->only_break_out($breakTime)->create([
                        'attendance_correction_request_id' => $attendanceCorrectionRequest->id,
                    ]);
                }
            }
        }
        if ($withoutBreakTime) {
            BreakTimeCorrectionRequest::factory()
                ->break_in_and_out()
                ->create([
                    'attendance_correction_request_id' => $withoutBreakTime->id,
                ]);
        }
    }
}
