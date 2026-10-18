<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    /**
     * 勤怠登録画面を表示し、当日の勤務状態を設定する。
     */
    public function create(): View
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

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

    /**
     * 出勤・退勤・休憩の打刻を登録する。
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $attendance = $user->attendances()
            ->where('date', today())
            ->first();

        if ($request->input('action') === 'clock_in') {

            if (! $attendance) {
                $user->attendances()->create([
                    'date' => today(),
                    'clock_in' => now()->format('H:i:s'),
                ]);
            } elseif ($attendance->clock_in === null) {
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

    /**
     * ログインユーザーの指定月の勤怠一覧を表示する。
     */
    public function index(Request $request): View
    {
        $date = Carbon::parse($request->query('date', now()->format('Y-m')));
        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        /** @var User $user */
        $user = Auth::guard('web')->user();

        $attendances = $user->attendances()
            ->with('breakTimes')
            ->whereYear('date', $date->year)
            ->whereMonth('date', $date->month)
            ->get();

        $formattedAttendanceRecords = $attendances->map(
            function ($attendance) {
                $totalBreakMinutes = $attendance->breakTimes
                    ->filter(fn ($breakTime) => $breakTime->break_out !== null)
                    ->sum(function ($breakTime) {
                        $breakIn = Carbon::parse($breakTime->break_in);
                        $breakOut = Carbon::parse($breakTime->break_out);

                        return $breakOut->diffInMinutes($breakIn);
                    });

                $breakHours = intdiv($totalBreakMinutes, 60);
                $breakMinutes = $totalBreakMinutes % 60;

                $breakTimeFormatted = sprintf(
                    '%d:%02d',
                    $breakHours,
                    $breakMinutes
                );

                $totalTimeFormatted = null;

                if (
                    $attendance->clock_in !== null
                    && $attendance->clock_out !== null
                ) {
                    $clockIn = Carbon::parse($attendance->clock_in);
                    $clockOut = Carbon::parse($attendance->clock_out);

                    $totalMinutes = $clockOut->diffInMinutes($clockIn);
                    $totalTime = $totalMinutes - $totalBreakMinutes;

                    if ($totalTime >= 0) {
                        $hours = intdiv($totalTime, 60);
                        $minutes = $totalTime % 60;

                        $totalTimeFormatted = sprintf(
                            '%d:%02d',
                            $hours,
                            $minutes
                        );
                    }
                }

                return [
                    'id' => $attendance->id,
                    'date' => $attendance->date,
                    'clock_in' => $attendance->clock_in
                        ? Carbon::parse($attendance->clock_in)->format('H:i')
                        : '',
                    'clock_out' => $attendance->clock_out
                        ? Carbon::parse($attendance->clock_out)->format('H:i')
                        : '',
                    'total_break_time' => $breakTimeFormatted,
                    'total_time' => $totalTimeFormatted,
                ];
            }
        )->all();

        return view('user.user-attendance-list', compact(
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }

    /**
     * 管理者向けに指定日の全ユーザーの勤怠一覧を表示する。
     */
    public function adminIndex(Request $request): View
    {
        abort_unless(Auth::guard('web')->user()?->admin_status, 403);

        $date = Carbon::parse(
            $request->query('date', now()->format('Y-m-d'))
        );

        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        $users = User::where('admin_status', false)->get();

        $attendanceRecords = Attendance::whereDate('date', $date)
            ->with('breakTimes')
            ->get()
            ->map(function ($attendance) {
                $totalBreakMinutes = $attendance->breakTimes
                    ->filter(fn ($breakTime) => $breakTime->break_out !== null)
                    ->sum(function ($breakTime) {
                        $breakIn = Carbon::parse($breakTime->break_in);
                        $breakOut = Carbon::parse($breakTime->break_out);

                        return $breakOut->diffInMinutes($breakIn);
                    });

                $breakHours = intdiv($totalBreakMinutes, 60);
                $breakMinutes = $totalBreakMinutes % 60;

                $attendance->total_break_time = sprintf(
                    '%d:%02d',
                    $breakHours,
                    $breakMinutes
                );

                $attendance->total_time = null;

                if (
                    $attendance->clock_in !== null
                    && $attendance->clock_out !== null
                ) {
                    $clockIn = Carbon::parse($attendance->clock_in);
                    $clockOut = Carbon::parse($attendance->clock_out);

                    $totalMinutes = $clockOut->diffInMinutes($clockIn);
                    $totalTime = $totalMinutes - $totalBreakMinutes;

                    if ($totalTime >= 0) {
                        $hours = intdiv($totalTime, 60);
                        $minutes = $totalTime % 60;

                        $attendance->total_time = sprintf(
                            '%d:%02d',
                            $hours,
                            $minutes
                        );
                    }
                }

                return $attendance;
            });

        return view(
            'admin.admin-attendance-list',
            compact(
                'date',
                'previousDay',
                'nextDay',
                'users',
                'attendanceRecords'
            )
        );
    }

    /**
     * 勤怠詳細を表示する。管理者と一般ユーザーで表示を切り替える。
     */
    public function show(int $id): View
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        abort_unless($user, 403);

        if ($user->admin_status) {

            $attendance = Attendance::with('breakTimes')
                ->findOrFail($id);

            $breaks = [];

            foreach ($attendance->breakTimes as $breakTime) {
                $breaks[] = [
                    'break_in' => $breakTime->break_in ? Carbon::parse($breakTime->break_in)->format('H:i') : '',
                    'break_out' => $breakTime->break_out ? Carbon::parse($breakTime->break_out)->format('H:i') : '',
                ];

            }

            $attendanceRecord = [
                'id' => $attendance->id,
                'year' => Carbon::parse($attendance->date)->format('Y年'),
                'date' => Carbon::parse($attendance->date)->format('m月d日'),
                'clock_in' => $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : '',
                'clock_out' => $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : '',
                'breaks' => $breaks,
                'comment' => $attendance->comment ?? '',
            ];

            return view('admin.admin-detail', compact('user', 'attendanceRecord'));
        }

        $attendance = $user->attendances()
            ->where('id', $id)
            ->firstOrFail();

        $application = AttendanceCorrectionRequest::with(
            'breakTimeCorrectionRequests'
        )
            ->where('attendance_id', $attendance->id)
            ->where('status', 'pending')
            ->latest('id')
            ->first();

        $breakTimes = $attendance->breakTimes()
            ->orderBy('id')
            ->get();

        $breaks = [];

        foreach ($attendance->breakTimes as $breakTime) {
            $breaks[] = [
                'break_in' => $breakTime->break_in ? Carbon::parse($breakTime->break_in)->format('H:i') : '',
                'break_out' => $breakTime->break_out ? Carbon::parse($breakTime->break_out)->format('H:i') : '',
            ];
        }

        $clockIn = $attendance->clock_in;
        $clockOut = $attendance->clock_out;
        $comment = '';

        if ($application !== null) {
            $clockIn = $application->new_clock_in;
            $clockOut = $application->new_clock_out;
            $comment = $application->comment;

            $breaks = [];

            foreach ($attendance->breakTimes as $breakTime) {
                $breaks[] = [
                    'break_in' => $breakTime->break_in ? Carbon::parse($breakTime->break_in)->format('H:i') : '',
                    'break_out' => $breakTime->break_out ? Carbon::parse($breakTime->break_out)->format('H:i') : '',
                ];
            }

        }

        $data = [
            'id' => $attendance->id,
            'year' => Carbon::parse($attendance->date)->format('Y年'),
            'date' => Carbon::parse($attendance->date)->format('m月d日'),
            'clock_in' => $clockIn ? Carbon::parse($clockIn)->format('H:i') : '',
            'clock_out' => $clockOut ? Carbon::parse($clockOut)->format('H:i') : '',
            'breaks' => array_map(function ($break) {
                return [
                    'break_in' => $break['break_in']
                        ? Carbon::parse($break['break_in'])->format('H:i')
                        : '',
                    'break_out' => $break['break_out']
                        ? Carbon::parse($break['break_out'])->format('H:i')
                        : '',
                ];
            }, $breaks),
            'comment' => $comment,
            'application' => $application,
        ];

        return view(
            'user.user-detail',
            compact('user', 'data')
        );
    }

    /**
     * 管理者向けに指定スタッフの勤怠一覧を表示する。
     */
    public function staffAttendance(Request $request, int $id): View
    {
        abort_unless(Auth::guard('web')->user()?->admin_status, 403);

        $user = User::where('admin_status', false)
            ->findOrFail($id);

        $date = Carbon::parse(
            $request->query('date', now()->format('Y-m'))
        );

        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $attendances = $user->attendances()
            ->whereYear('date', $date->year)
            ->whereMonth('date', $date->month)
            ->get();

        $formattedAttendanceRecords = [];

        foreach ($attendances as $attendance) {
            $totalBreakMinutes = 0;

            foreach ($attendance->breakTimes as $breakTime) {
                if (
                    $breakTime->break_in === null ||
                    $breakTime->break_out === null
                ) {
                    continue;
                }

                $breakIn = Carbon::parse($breakTime->break_in);
                $breakOut = Carbon::parse($breakTime->break_out);

                $totalBreakMinutes += $breakOut->diffInMinutes($breakIn);
            }

            $breakHours = intdiv($totalBreakMinutes, 60);
            $breakMinutes = $totalBreakMinutes % 60;

            $breakTimeFormatted = sprintf(
                '%d:%02d',
                $breakHours,
                $breakMinutes
            );

            $totalTimeFormatted = null;

            if (
                $attendance->clock_in !== null &&
                $attendance->clock_out !== null
            ) {
                $clockIn = Carbon::parse($attendance->clock_in);
                $clockOut = Carbon::parse($attendance->clock_out);

                $totalMinutes = $clockOut->diffInMinutes($clockIn);
                $totalTime = $totalMinutes - $totalBreakMinutes;

                $hours = intdiv($totalTime, 60);
                $minutes = $totalTime % 60;

                $totalTimeFormatted = sprintf(
                    '%d:%02d',
                    $hours,
                    $minutes
                );
            }

            $formattedAttendanceRecords[] = [
                'id' => $attendance->id,
                'date' => $attendance->date,
                'clock_in' => $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : '',
                'clock_out' => $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : '',
                'total_break_time' => $breakTimeFormatted,
                'total_time' => $totalTimeFormatted,
            ];
        }

        return view('admin.staff-attendance-list', compact(
            'user',
            'date',
            'previousMonth',
            'nextMonth',
            'formattedAttendanceRecords'
        ));
    }

    /**
     * 指定スタッフ・指定月の勤怠をCSV形式で出力する。
     */
    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'year_month' => [
                'required',
                'date_format:Y-m',
            ],
        ]);

        $user = User::where('admin_status', false)
            ->findOrFail($validated['user_id']);

        $date = Carbon::createFromFormat(
            '!Y-m',
            $validated['year_month']
        );

        $attendances = $user->attendances()
            ->whereYear('date', $date->year)
            ->whereMonth('date', $date->month)
            ->with('breakTimes')
            ->orderBy('date')
            ->get();

        $fileName = sprintf(
            'attendance_%s_%s.csv',
            $user->id,
            $validated['year_month']
        );

        return response()->streamDownload(
            function () use ($attendances) {
                $stream = fopen('php://output', 'w');

                fwrite($stream, "\xEF\xBB\xBF");

                fputcsv($stream, [
                    '日付',
                    '出勤',
                    '退勤',
                    '休憩',
                    '合計',
                ]);

                foreach ($attendances as $attendance) {
                    $totalBreakMinutes = $attendance->breakTimes
                        ->filter(function ($breakTime) {
                            return $breakTime->break_in !== null
                                && $breakTime->break_out !== null;
                        })
                        ->sum(function ($breakTime) {
                            $breakIn = Carbon::parse($breakTime->break_in);
                            $breakOut = Carbon::parse($breakTime->break_out);

                            return $breakOut->diffInMinutes($breakIn);
                        });

                    $breakHours = intdiv($totalBreakMinutes, 60);
                    $breakMinutes = $totalBreakMinutes % 60;

                    $breakTimeFormatted = sprintf(
                        '%d:%02d',
                        $breakHours,
                        $breakMinutes
                    );

                    $totalTimeFormatted = '';

                    if (
                        $attendance->clock_in !== null
                        && $attendance->clock_out !== null
                    ) {
                        $clockIn = Carbon::parse($attendance->clock_in);
                        $clockOut = Carbon::parse($attendance->clock_out);

                        $totalMinutes = $clockOut->diffInMinutes($clockIn);
                        $workMinutes = $totalMinutes - $totalBreakMinutes;

                        if ($workMinutes >= 0) {
                            $totalTimeFormatted = sprintf(
                                '%d:%02d',
                                intdiv($workMinutes, 60),
                                $workMinutes % 60
                            );
                        }
                    }

                    fputcsv($stream, [
                        Carbon::parse($attendance->date)->format('Y/m/d'),
                        $attendance->clock_in
                            ? Carbon::parse($attendance->clock_in)->format('H:i')
                            : '',
                        $attendance->clock_out
                            ? Carbon::parse($attendance->clock_out)->format('H:i')
                            : '',
                        $breakTimeFormatted,
                        $totalTimeFormatted,
                    ]);
                }

                fclose($stream);
            },
            $fileName,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
    }

    /**
     * マイ勤怠レポート画面を表示する。
     */
    public function report(): View
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $startDate = now()->startOfMonth()->subMonths(5);
        $endDate = now()->endOfMonth();

        $attendances = $user->attendances()
            ->with('breakTimes')
            ->whereBetween('date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->orderBy('date')
            ->get();

        $dailyRecords = $attendances
            ->filter(function ($attendance) {
                return $attendance->clock_in !== null
                    && $attendance->clock_out !== null;
            })
            ->map(function ($attendance) {
                $clockIn = Carbon::parse($attendance->clock_in);
                $clockOut = Carbon::parse($attendance->clock_out);

                $totalBreakMinutes = $attendance->breakTimes
                    ->filter(function ($breakTime) {
                        return $breakTime->break_in !== null
                            && $breakTime->break_out !== null;
                    })
                    ->sum(function ($breakTime) {
                        $breakIn = Carbon::parse($breakTime->break_in);
                        $breakOut = Carbon::parse($breakTime->break_out);

                        return $breakOut->diffInMinutes($breakIn);
                    });

                $elapsedMinutes = $clockOut->diffInMinutes($clockIn);
                $workMinutes = $elapsedMinutes - $totalBreakMinutes;

                if ($workMinutes < 0) {
                    return null;
                }

                return [
                    'date' => Carbon::parse($attendance->date),
                    'clock_in' => $clockIn,
                    'clock_out' => $clockOut,
                    'work_minutes' => $workMinutes,
                    'overtime_minutes' => max(0, $workMinutes - 480),
                ];
            })
            ->filter()
            ->values();

        $totalWorkMinutes = $dailyRecords->sum('work_minutes');

        $totalOvertimeMinutes = $dailyRecords->sum('overtime_minutes');

        $avgWorkMinutes = $dailyRecords->isNotEmpty()
            ? (int) round($totalWorkMinutes / $dailyRecords->count())
            : 0;

        $monthlyTrend = collect(range(0, 5))
            ->map(function ($monthOffset) use ($startDate, $dailyRecords) {
                $month = $startDate->copy()->addMonths($monthOffset);
                $monthKey = $month->format('Y-m');

                $records = $dailyRecords->filter(function ($record) use ($monthKey) {
                    return $record['date']->format('Y-m') === $monthKey;
                });

                return [
                    'month' => $month->format('Y年n月'),
                    'work_minutes' => $records->sum('work_minutes'),
                    'overtime_minutes' => $records->sum('overtime_minutes'),
                ];
            });

        $currentMonthRecords = $dailyRecords->filter(function ($record) {
            return $record['date']->isSameMonth(now());
        });

        $anomalies = [
            'late_count' => $currentMonthRecords
                ->filter(function ($record) {
                    return $record['clock_in']->format('H:i:s') > '09:00:00';
                })
                ->count(),

            'early_leave_count' => $currentMonthRecords
                ->filter(function ($record) {
                    return $record['clock_out']->format('H:i:s') < '18:00:00';
                })
                ->count(),

            'long_work_count' => $currentMonthRecords
                ->filter(function ($record) {
                    return $record['work_minutes'] > 600;
                })
                ->count(),
        ];

        return view('reports.index', [
            'summary' => [
                'total_work_minutes' => $totalWorkMinutes,
                'total_overtime_minutes' => $totalOvertimeMinutes,
                'avg_work_minutes' => $avgWorkMinutes,
            ],
            'monthlyTrend' => $monthlyTrend,
            'anomalies' => $anomalies,
        ]);
    }
}
