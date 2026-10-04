<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function create()
    {
        /** @var User $user */
        $user = auth()->user();

        $formattedDate = now()->format('Y/m/d');
        $formattedTime = now()->format('H:i');

        $attendance = $user->attendances()
            ->where('date', today())
            ->first();

        if (! $attendance || $attendance->clock_in === null) {

            $user->attendance_status = '勤務外';

        } elseif ($attendance->clock_out !== null) {

            $user->attendance_status = '退勤済';

        } elseif (
            $attendance->breakTimes()
                ->whereNotNull('break_in')
                ->whereNull('break_out')
                ->exists()
        ) {

            $user->attendance_status = '休憩中';

        } else {

            $user->attendance_status = '出勤中';

        }

        return view(
            'user.attendance-register',
            compact('user', 'formattedDate', 'formattedTime')
        );
    }

    public function store(Request $request)
    {
        /** @var User $user */
        $user = auth()->user();

        $attendance = $user->attendances()
            ->where('date', today())
            ->first();

        if ($request->input('action') === 'clock_in') {

            if (! $attendance) {
                $attendance = $user->attendances()->create([
                    'date' => today(),
                    'clock_in' => now()->format('H:i:s'),
                ]);
            } else {
                $attendance->update([
                    'clock_in' => now()->format('H:i:s'),
                ]);
            }
        } elseif ($request->input('action') === 'clock_out') {

            $attendance->update([
                'clock_out' => now()->format('H:i:s'),
            ]);
        } elseif ($request->input('action') === 'break_in') {

            $attendance->breakTimes()->create([
                'break_in' => now()->format('H:i:s'),
            ]);
        } elseif ($request->input('action') === 'break_out') {

            $breakTime = $attendance->breakTimes()
                ->whereNotNull('break_in')
                ->whereNull('break_out')
                ->first();

            $breakTime->update([
                'break_out' => now()->format('H:i:s'),
            ]);
        }

        return redirect('/attendance');
    }
}
