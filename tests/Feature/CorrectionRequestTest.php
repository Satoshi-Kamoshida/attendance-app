<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\BreakTimeCorrectionRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CorrectionRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_list_shows_only_own_applications(): void
    {
        $userA = User::factory()->create(['admin_status' => 0]);
        $userB = User::factory()->create(['admin_status' => 0]);

        $attendanceA = Attendance::factory()->create([
            'user_id' => $userA->id,
            'date' => '2026-10-14',
        ]);

        $attendanceB = Attendance::factory()->create([
            'user_id' => $userB->id,
            'date' => '2026-10-14',
        ]);

        $applicationA = AttendanceCorrectionRequest::factory()->create([
            'attendance_id' => $attendanceA->id,
            'status' => 'pending',
            'comment' => '出勤時刻の修正',
        ]);

        AttendanceCorrectionRequest::factory()->create([
            'attendance_id' => $attendanceB->id,
            'status' => 'pending',
            'comment' => '他ユーザーの申請',
        ]);

        $response = $this->actingAs($userA)
            ->get('/stamp_correction_request/list');

        $response->assertOk();

        $response->assertViewHas(
            'formattedApplications',
            function ($applications) use ($applicationA) {
                return count($applications) === 1
                    && $applications[0]['id'] === $applicationA->id
                    && $applications[0]['approval_status'] === '承認待ち'
                    && $applications[0]['comment'] === '出勤時刻の修正';
            }
        );
    }

    public function test_user_can_submit_attendance_correction_request(): void
    {
        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->post(
            '/attendance/'.$attendance->id,
            [
                'new_clock_in' => '09:15',
                'new_clock_out' => '18:15',
                'comment' => '打刻時刻の修正',
            ]
        );

        $response->assertRedirect('/stamp_correction_request/list');

        $this->assertDatabaseHas('attendance_correction_requests', [
            'attendance_id' => $attendance->id,
            'new_clock_in' => '09:15',
            'new_clock_out' => '18:15',
            'comment' => '打刻時刻の修正',
            'status' => 'pending',
        ]);
    }

    public function test_user_cannot_submit_another_request_while_pending(): void
    {
        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        AttendanceCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'new_clock_in' => '09:15',
            'new_clock_out' => '18:15',
            'comment' => '既存の申請',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->post(
            '/attendance/'.$attendance->id,
            [
                'new_clock_in' => '09:30',
                'new_clock_out' => '18:30',
                'comment' => '追加の申請',
            ]
        );

        $response->assertForbidden();

        $this->assertDatabaseCount('attendance_correction_requests', 1);
    }

    public function test_attendance_correction_rejects_invalid_input(): void
    {
        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $baseInput = [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'comment' => '修正理由',
        ];

        $cases = [
            '出勤時刻が退勤時刻より後' => [
                'input' => [
                    'new_clock_in' => '18:30',
                    'new_clock_out' => '09:00',
                ],
                'error' => 'new_clock_out',
            ],
            '休憩開始が出勤時刻より前' => [
                'input' => [
                    'new_break_in' => ['08:30'],
                    'new_break_out' => ['08:45'],
                ],
                'error' => 'new_break_in.0',
            ],
            '休憩開始が退勤時刻より後' => [
                'input' => [
                    'new_break_in' => ['18:30'],
                    'new_break_out' => ['18:45'],
                ],
                'error' => 'new_break_in.0',
            ],
            '休憩終了が休憩開始以前' => [
                'input' => [
                    'new_break_in' => ['12:30'],
                    'new_break_out' => ['12:00'],
                ],
                'error' => 'new_break_out.0',
            ],
            '休憩終了が退勤時刻より後' => [
                'input' => [
                    'new_break_in' => ['17:30'],
                    'new_break_out' => ['18:30'],
                ],
                'error' => 'new_break_out.0',
            ],
            '備考が未入力' => [
                'input' => [
                    'comment' => '',
                ],
                'error' => 'comment',
            ],
        ];

        foreach ($cases as $caseName => $case) {
            $input = array_merge($baseInput, $case['input']);

            $response = $this->actingAs($user)->post(
                '/attendance/'.$attendance->id,
                $input
            );

            $response->assertSessionHasErrors([
                $case['error'],
            ]);

            $this->assertDatabaseCount('attendance_correction_requests', 0);
        }
    }

    public function test_admin_can_view_correction_applications_and_detail(): void
    {
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $application = AttendanceCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'new_clock_in' => '09:15:00',
            'new_clock_out' => '18:15:00',
            'comment' => '管理者確認用',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->get('/stamp_correction_request/list')
            ->assertOk()
            ->assertViewHas('applications', function ($applications) use (
                $application
            ) {
                return $applications->contains(
                    'id',
                    $application->id
                );
            });

        $this->get(
            '/stamp_correction_request/approve/'.$application->id
        )
            ->assertOk()
            ->assertViewHas('application', function ($viewApplication) use (
                $application
            ) {
                return $viewApplication->id === $application->id
                    && $viewApplication->new_clock_in === '09:15'
                    && $viewApplication->new_clock_out === '18:15';
            })
            ->assertViewHas('user', function ($viewUser) use ($user) {
                return $viewUser->id === $user->id;
            });
    }

    public function test_admin_approval_updates_attendance_and_break_times(): void
    {
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $firstBreak = $attendance->breakTimes()->create([
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $secondBreak = $attendance->breakTimes()->create([
            'break_in' => '15:00:00',
            'break_out' => '15:15:00',
        ]);

        $application = AttendanceCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'new_clock_in' => '09:15:00',
            'new_clock_out' => '18:15:00',
            'comment' => '出退勤時刻を修正',
            'status' => 'pending',
        ]);

        BreakTimeCorrectionRequest::create([
            'attendance_correction_request_id' => $application->id,
            'break_time_id' => $firstBreak->id,
            'new_break_in' => '12:15:00',
            'new_break_out' => '13:00:00',
        ]);

        BreakTimeCorrectionRequest::create([
            'attendance_correction_request_id' => $application->id,
            'break_time_id' => $secondBreak->id,
            'new_break_in' => null,
            'new_break_out' => null,
        ]);

        BreakTimeCorrectionRequest::create([
            'attendance_correction_request_id' => $application->id,
            'break_time_id' => null,
            'new_break_in' => '16:00:00',
            'new_break_out' => '16:15:00',
        ]);

        $this->actingAs($admin)
            ->post(
                '/stamp_correction_request/approve/'.$application->id
            )
            ->assertRedirect('/stamp_correction_request/list');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '09:15:00',
            'clock_out' => '18:15:00',
        ]);

        $this->assertDatabaseHas('attendance_correction_requests', [
            'id' => $application->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('break_times', [
            'id' => $firstBreak->id,
            'break_in' => '12:15:00',
            'break_out' => '13:00:00',
        ]);

        $this->assertDatabaseMissing('break_times', [
            'id' => $secondBreak->id,
        ]);

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'break_in' => '16:00:00',
            'break_out' => '16:15:00',
        ]);

        $this->assertDatabaseCount('break_times', 2);
    }

    public function test_admin_approval_rejects_invalid_or_already_approved_request(): void
    {
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $missingClockIn = AttendanceCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'new_clock_in' => null,
            'new_clock_out' => '18:15:00',
            'comment' => '出勤時刻なし',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(
                '/stamp_correction_request/approve/'.$missingClockIn->id
            )
            ->assertStatus(409);

        $this->assertDatabaseHas('attendance_correction_requests', [
            'id' => $missingClockIn->id,
            'status' => 'pending',
        ]);

        $approved = AttendanceCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'new_clock_in' => '09:15:00',
            'new_clock_out' => '18:15:00',
            'comment' => '承認済み',
            'status' => 'approved',
        ]);

        $this->post(
            '/stamp_correction_request/approve/'.$approved->id
        )->assertStatus(409);
    }

    public function test_user_cannot_open_another_users_application_detail(): void
    {
        $userA = User::factory()->create([
            'admin_status' => 0,
        ]);

        $userB = User::factory()->create([
            'admin_status' => 0,
        ]);

        $attendanceB = Attendance::factory()->create([
            'user_id' => $userB->id,
            'date' => '2026-10-14',
        ]);

        $applicationB = AttendanceCorrectionRequest::create([
            'attendance_id' => $attendanceB->id,
            'new_clock_in' => '09:15:00',
            'new_clock_out' => '18:15:00',
            'comment' => '他人の申請',
            'status' => 'pending',
        ]);

        $this->actingAs($userA)
            ->get('/application/'.$applicationB->id)
            ->assertNotFound();
    }

    public function test_application_list_shows_own_pending_and_approved_requests_only(): void
    {
        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $otherUser = User::factory()->create([
            'admin_status' => 0,
        ]);

        $ownAttendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $otherAttendance = Attendance::factory()->create([
            'user_id' => $otherUser->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $pendingApplication = AttendanceCorrectionRequest::create([
            'attendance_id' => $ownAttendance->id,
            'new_clock_in' => '09:15:00',
            'new_clock_out' => '18:15:00',
            'comment' => '承認待ちの申請',
            'status' => 'pending',
        ]);

        $approvedApplication = AttendanceCorrectionRequest::create([
            'attendance_id' => $ownAttendance->id,
            'new_clock_in' => '09:30:00',
            'new_clock_out' => '18:30:00',
            'comment' => '承認済みの申請',
            'status' => 'approved',
        ]);

        $otherApplication = AttendanceCorrectionRequest::create([
            'attendance_id' => $otherAttendance->id,
            'new_clock_in' => '09:45:00',
            'new_clock_out' => '18:45:00',
            'comment' => '他人の申請',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)
            ->get('/stamp_correction_request/list');

        $response->assertOk();

        $response->assertViewHas(
            'formattedApplications',
            function ($applications) use (
                $pendingApplication,
                $approvedApplication,
                $otherApplication
            ) {
                $ids = collect($applications)
                    ->pluck('id')
                    ->all();

                $pending = collect($applications)->firstWhere(
                    'id',
                    $pendingApplication->id
                );

                $approved = collect($applications)->firstWhere(
                    'id',
                    $approvedApplication->id
                );

                return in_array($pendingApplication->id, $ids, true)
                    && in_array($approvedApplication->id, $ids, true)
                    && ! in_array($otherApplication->id, $ids, true)
                    && $pending['approval_status'] === '承認待ち'
                    && $approved['approval_status'] === '承認済み';
            }
        );
    }

    public function test_admin_approval_rejects_inconsistent_break_times(): void
    {
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '変更前の備考',
        ]);

        $breakTime = $attendance->breakTimes()->create([
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $invalidOrderApplication = AttendanceCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'new_clock_in' => '09:15:00',
            'new_clock_out' => '18:15:00',
            'comment' => '休憩時刻の順序が不正',
            'status' => 'pending',
        ]);

        BreakTimeCorrectionRequest::create([
            'attendance_correction_request_id' => $invalidOrderApplication->id,
            'break_time_id' => $breakTime->id,
            'new_break_in' => '13:30:00',
            'new_break_out' => '13:00:00',
        ]);

        $incompleteApplication = AttendanceCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'new_clock_in' => '09:30:00',
            'new_clock_out' => '18:30:00',
            'comment' => '休憩時刻が不完全',
            'status' => 'pending',
        ]);

        BreakTimeCorrectionRequest::create([
            'attendance_correction_request_id' => $incompleteApplication->id,
            'break_time_id' => $breakTime->id,
            'new_break_in' => '12:15:00',
            'new_break_out' => null,
        ]);

        $this->actingAs($admin);

        $this->post(
            '/stamp_correction_request/approve/'.$invalidOrderApplication->id
        )->assertStatus(409);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '変更前の備考',
        ]);

        $this->assertDatabaseHas('break_times', [
            'id' => $breakTime->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $this->assertDatabaseHas('attendance_correction_requests', [
            'id' => $invalidOrderApplication->id,
            'status' => 'pending',
        ]);

        $this->post(
            '/stamp_correction_request/approve/'.$incompleteApplication->id
        )->assertStatus(409);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $this->assertDatabaseHas('attendance_correction_requests', [
            'id' => $incompleteApplication->id,
            'status' => 'pending',
        ]);
    }

    public function test_admin_application_list_shows_pending_and_approved_requests(): void
    {
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $user = User::factory()->create([
            'name' => '申請確認スタッフ',
            'admin_status' => 0,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $pendingApplication = AttendanceCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'new_clock_in' => '09:15:00',
            'new_clock_out' => '18:15:00',
            'comment' => '承認待ちの確認',
            'status' => 'pending',
        ]);

        $approvedApplication = AttendanceCorrectionRequest::create([
            'attendance_id' => $attendance->id,
            'new_clock_in' => '09:30:00',
            'new_clock_out' => '18:30:00',
            'comment' => '承認済みの確認',
            'status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->get('/stamp_correction_request/list')
            ->assertOk()
            ->assertViewIs('admin.admin-application-list')
            ->assertViewHas('applications', function ($applications) use (
                $pendingApplication,
                $approvedApplication,
                $user
            ) {
                $pending = $applications->firstWhere(
                    'id',
                    $pendingApplication->id
                );

                $approved = $applications->firstWhere(
                    'id',
                    $approvedApplication->id
                );

                return $pending !== null
                    && $approved !== null
                    && $pending->approval_status === '承認待ち'
                    && $approved->approval_status === '承認済み'
                    && $pending->user->id === $user->id
                    && $approved->user->id === $user->id
                    && $pending->AttendanceRecord->date === '2026-10-14'
                    && $approved->AttendanceRecord->date === '2026-10-14'
                    && $pending->comment === '承認待ちの確認'
                    && $approved->comment === '承認済みの確認';
            });
    }
}
