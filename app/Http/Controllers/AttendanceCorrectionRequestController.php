<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminAttendanceUpdateRequest;
use App\Http\Requests\StoreAttendanceCorrectionRequest;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\BreakTimeCorrectionRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceCorrectionRequestController extends Controller
{
    /**
     * ユーザー種別に応じて勤怠修正処理を振り分ける。
     */
    public function store(Request $request, int $id): RedirectResponse
    {
        $user = auth()->user();

        if ($user->admin_status) {
            $adminRequest = app(AdminAttendanceUpdateRequest::class);

            return $this->adminUpdate($adminRequest, $id);
        }

        $userRequest = app(StoreAttendanceCorrectionRequest::class);

        return $this->storeCorrection($userRequest, $id);
    }

    /**
     * 一般ユーザーの勤怠修正申請を登録する。
     */
    public function storeCorrection(StoreAttendanceCorrectionRequest $request, int $id): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $attendance = $user->attendances()
            ->where('id', $id)
            ->firstOrFail();

        $hasPendingApplication = AttendanceCorrectionRequest::where(
            'attendance_id',
            $attendance->id
        )
            ->where('status', 'pending')
            ->exists();

        if ($hasPendingApplication) {
            abort(403, '承認待ちのため修正できません。');
        }

        $validated = $request->validated();

        $correctionRequest = AttendanceCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'new_clock_in' => $validated['new_clock_in'] ?? null,
            'new_clock_out' => $validated['new_clock_out'] ?? null,
            'comment' => $validated['comment'],
            'status' => 'pending',
        ]);

        $breakTimes = $attendance->breakTimes()
            ->orderBy('id')
            ->get();

        foreach ($validated['new_break_in'] ?? [] as $index => $newBreakIn) {
            $newBreakOut = $validated['new_break_out'][$index] ?? null;

            $breakTimeId = $breakTimes->get($index)?->id;

            if (empty($newBreakIn) && empty($newBreakOut)
                && $breakTimeId === null) {
                continue;
            }

            BreakTimeCorrectionRequest::create([
                'attendance_correction_request_id' => $correctionRequest->id,
                'break_time_id' => $breakTimeId,
                'new_break_in' => $newBreakIn,
                'new_break_out' => $newBreakOut,
            ]);
        }

        return redirect('/stamp_correction_request/list');
    }

    /**
     * ログインユーザーに応じた修正申請一覧を表示する。
     */
    public function index(): View
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        abort_unless($user, 403);

        if ($user->admin_status) {
            return $this->adminIndex();
        }

        $applications = AttendanceCorrectionRequest::whereHas(
            'attendance',
            function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }
        )
            ->with('attendance')
            ->orderByDesc('created_at')
            ->get();

        $formattedApplications = $applications->map(
            function ($application) {
                return [
                    'id' => $application->id,
                    'approval_status' => $application->status === 'pending'
                        ? '承認待ち'
                        : '承認済み',
                    'date' => $application->attendance->date,
                    'comment' => $application->comment,
                    'application_date' => $application->created_at,
                ];
            }
        )->all();

        return view(
            'user.user-application-list',
            compact('user', 'formattedApplications')
        );
    }

    /**
     * 管理者向けに全修正申請の一覧を表示する。
     */
    public function adminIndex(): View
    {
        $applications = AttendanceCorrectionRequest::with([
            'attendance.user',
        ])
            ->orderByDesc('created_at')
            ->get();

        return view(
            'admin.admin-application-list',
            compact('applications')
        );
    }

    /**
     * 管理者が勤怠と休憩時間を直接修正する。
     */
    public function adminUpdate(AdminAttendanceUpdateRequest $request, int $id): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();
        abort_unless($user?->admin_status, 403);

        $attendance = Attendance::with(['breakTimes' => function ($query) {
            $query->orderBy('id');
        }, ])
            ->findOrFail($id);

        $validated = $request->validated();

        $attendance->clock_in = $validated['new_clock_in'] ?? null;
        $attendance->clock_out = $validated['new_clock_out'] ?? null;
        $attendance->comment = $validated['comment'];

        $attendance->save();

        $breakTimes = $attendance->breakTimes;

        foreach ($validated['new_break_in'] ?? [] as $index => $newBreakIn) {

            $newBreakOut = $validated['new_break_out'][$index] ?? null;

            $breakTime = $breakTimes->get($index);

            if (empty($newBreakIn) && empty($newBreakOut)) {
                if ($breakTime) {
                    $breakTime->delete();
                }

                continue;
            }

            if ($breakTime) {

                $breakTime->break_in = $newBreakIn;
                $breakTime->break_out = $newBreakOut;
                $breakTime->save();

                continue;
            }

            $attendance->breakTimes()->create([
                'break_in' => $newBreakIn,
                'break_out' => $newBreakOut,
            ]);
        }

        return redirect('/admin/attendance/list');
    }

    /**
     * 管理者向けに修正申請の詳細を表示する。
     */
    public function show(int $id): View
    {
        $user = Auth::guard('web')->user();

        abort_unless($user?->admin_status, 403);

        $application = AttendanceCorrectionRequest::with([
            'attendance.user',
            'breakTimeCorrectionRequests',
        ])->findOrFail($id);

        if ($application->new_clock_in !== null) {
            $application->new_clock_in = Carbon::parse(
                $application->new_clock_in
            )->format('H:i');
        }

        if ($application->new_clock_out !== null) {
            $application->new_clock_out = Carbon::parse(
                $application->new_clock_out
            )->format('H:i');
        }

        $user = $application->attendance->user;

        return view('admin.admin-application-detail', compact('application', 'user'));

    }

    /**
     * 修正申請を承認し、勤怠と休憩時間に反映する。
     */
    public function update(int $id): RedirectResponse
    {
        $user = Auth::guard('web')->user();
        abort_unless($user?->admin_status, 403);

        DB::transaction(function () use ($id) {
            $application = AttendanceCorrectionRequest::with([
                'attendance.breakTimes',
                'breakTimeCorrectionRequests',
            ])->findOrFail($id);

            abort_unless($application->status === 'pending', 409);

            $attendance = $application->attendance;

            $newClockIn = $application->new_clock_in;
            $newClockOut = $application->new_clock_out;

            if (empty($newClockIn)) {
                abort(409, '出勤時刻が未入力のため、承認できません。');
            }

            if (! empty($newClockOut)) {
                $clockIn = Carbon::parse($newClockIn);
                $clockOut = Carbon::parse($newClockOut);

                if ($clockOut->lessThanOrEqualTo($clockIn)) {
                    abort(409, '退勤時刻は出勤時刻より後にしてください。');
                }
            }

            $attendance->clock_in = $newClockIn;
            $attendance->clock_out = $newClockOut;
            $attendance->save();

            $breakRequests = $application->breakTimeCorrectionRequests;

            $requestedBreakIds = $breakRequests
                ->pluck('break_time_id')
                ->filter()
                ->flip();

            foreach ($attendance->breakTimes as $breakTime) {
                if (! $requestedBreakIds->has($breakTime->id)) {
                    BreakTimeCorrectionRequest::where(
                        'break_time_id',
                        $breakTime->id
                    )->update([
                        'break_time_id' => null,
                    ]);

                    $breakTime->delete();
                }
            }

            foreach ($breakRequests as $breakRequest) {
                $newBreakIn = $breakRequest->new_break_in;
                $newBreakOut = $breakRequest->new_break_out;

                if (empty($newBreakIn) xor empty($newBreakOut)) {
                    abort(409, '休憩時間の開始・終了が不整合なため、承認できません。');
                }

                if (! empty($newBreakIn) && ! empty($newBreakOut)) {
                    $breakIn = Carbon::parse($newBreakIn);
                    $breakOut = Carbon::parse($newBreakOut);

                    if ($breakIn->greaterThanOrEqualTo($breakOut)) {
                        abort(
                            409,
                            '休憩開始時刻は休憩終了時刻より前にしてください。'
                        );
                    }
                }

                if ($breakRequest->break_time_id !== null) {
                    $breakTime = $attendance->breakTimes
                        ->firstWhere('id', $breakRequest->break_time_id);

                    abort_if($breakTime === null, 409);

                    if (empty($newBreakIn) && empty($newBreakOut)) {
                        BreakTimeCorrectionRequest::where(
                            'break_time_id',
                            $breakTime->id
                        )->update([
                            'break_time_id' => null,
                        ]);

                        $breakTime->delete();

                        continue;
                    }

                    $breakTime->break_in = $newBreakIn;
                    $breakTime->break_out = $newBreakOut;
                    $breakTime->save();

                    continue;
                }

                if (empty($newBreakIn) && empty($newBreakOut)) {
                    continue;
                }

                $attendance->breakTimes()->create([
                    'break_in' => $newBreakIn,
                    'break_out' => $newBreakOut,
                ]);
            }

            $application->status = 'approved';
            $application->save();
        });

        return redirect('/stamp_correction_request/list');
    }

    /**
     * ユーザー自身の修正申請詳細から勤怠詳細画面へ遷移する。
     */
    public function userShowApplication(int $id): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $application = AttendanceCorrectionRequest::whereHas(
            'attendance',
            function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }
        )->findOrFail($id);

        return redirect('/attendance/'.$application->attendance_id);
    }
}
