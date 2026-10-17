<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_admin_attendance_list(): void
    {
        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $response = $this->actingAs($user)
            ->get('/admin/attendance/list');

        $response->assertForbidden();
    }

    public function test_admin_can_access_admin_attendance_list(): void
    {
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/attendance/list');

        $response->assertOk();
    }

    public function test_admin_can_update_attendance_and_break_times(): void
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
            'comment' => '修正前',
        ]);

        $firstBreak = $attendance->breakTimes()->create([
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $secondBreak = $attendance->breakTimes()->create([
            'break_in' => '15:00:00',
            'break_out' => '15:15:00',
        ]);

        $response = $this->actingAs($admin)->post(
            '/attendance/'.$attendance->id,
            [
                'new_clock_in' => '09:15',
                'new_clock_out' => '18:15',
                'comment' => '管理者による修正',

                'new_break_in' => [
                    '12:15',
                    '',
                    '16:00',
                ],
                'new_break_out' => [
                    '13:00',
                    '',
                    '16:15',
                ],
            ]
        );

        $response->assertRedirect('/admin/attendance/list');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '09:15',
            'clock_out' => '18:15',
            'comment' => '管理者による修正',
        ]);

        $this->assertDatabaseHas('break_times', [
            'id' => $firstBreak->id,
            'break_in' => '12:15',
            'break_out' => '13:00',
        ]);

        $this->assertDatabaseMissing('break_times', [
            'id' => $secondBreak->id,
        ]);

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'break_in' => '16:00',
            'break_out' => '16:15',
        ]);

        $this->assertDatabaseCount('break_times', 2);
    }

    public function test_admin_attendance_and_staff_lists_show_correct_records(): void
    {
        $this->travelTo(
            Carbon::parse('2026-10-15 10:00:00')
        );

        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $userA = User::factory()->create([
            'name' => 'スタッフA',
            'admin_status' => 0,
        ]);

        $userB = User::factory()->create([
            'name' => 'スタッフB',
            'admin_status' => 0,
        ]);

        $todayAttendance = Attendance::factory()->create([
            'user_id' => $userA->id,
            'date' => '2026-10-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        Attendance::factory()->create([
            'user_id' => $userB->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => '17:00:00',
        ]);

        $this->actingAs($admin)
            ->get('/admin/attendance/list?date=2026-10-15')
            ->assertOk()
            ->assertViewHas('date', function ($date) {
                return $date->format('Y-m-d') === '2026-10-15';
            })
            ->assertViewHas('previousDay', '2026-10-14')
            ->assertViewHas('nextDay', '2026-10-16')
            ->assertViewHas('users', function ($users) {
                return $users->count() === 2;
            })
            ->assertViewHas('attendanceRecords', function ($records) use (
                $todayAttendance
            ) {
                return $records->count() === 1
                    && $records->first()->id === $todayAttendance->id;
            });

        Attendance::factory()->create([
            'user_id' => $userA->id,
            'date' => '2026-09-14',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $this->get(
            '/admin/attendance/staff/'.$userA->id.'?date=2026-10'
        )
            ->assertOk()
            ->assertViewHas('user', function ($viewUser) use ($userA) {
                return $viewUser->id === $userA->id;
            })
            ->assertViewHas('previousMonth', '2026-09')
            ->assertViewHas('nextMonth', '2026-11')
            ->assertViewHas(
                'formattedAttendanceRecords',
                function ($records) use ($todayAttendance) {
                    return count($records) === 1
                        && $records[0]['id'] === $todayAttendance->id;
                }
            );
    }

    public function test_admin_attendance_update_rejects_invalid_input(): void
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
            'comment' => '修正前の備考',
        ]);

        $invalidCases = [
            [
                'name' => '出勤時刻が退勤時刻より後',
                'input' => [
                    'new_clock_in' => '18:00',
                    'new_clock_out' => '09:00',
                    'new_break_in' => [],
                    'new_break_out' => [],
                    'comment' => '時刻の順序が不正',
                ],
                'error' => 'new_clock_out',
            ],
            [
                'name' => '休憩開始時刻が退勤時刻より後',
                'input' => [
                    'new_clock_in' => '09:00',
                    'new_clock_out' => '18:00',
                    'new_break_in' => ['18:30'],
                    'new_break_out' => ['18:45'],
                    'comment' => '休憩開始時刻が不正',
                ],
                'error' => 'new_break_in.0',
            ],
            [
                'name' => '休憩終了時刻が退勤時刻より後',
                'input' => [
                    'new_clock_in' => '09:00',
                    'new_clock_out' => '18:00',
                    'new_break_in' => ['17:30'],
                    'new_break_out' => ['18:30'],
                    'comment' => '休憩終了時刻が不正',
                ],
                'error' => 'new_break_out.0',
            ],
            [
                'name' => '休憩終了時刻が休憩開始時刻より前',
                'input' => [
                    'new_clock_in' => '09:00',
                    'new_clock_out' => '18:00',
                    'new_break_in' => ['12:30'],
                    'new_break_out' => ['12:00'],
                    'comment' => '休憩時刻の順序が不正',
                ],
                'error' => 'new_break_out.0',
            ],
            [
                'name' => '備考が未入力',
                'input' => [
                    'new_clock_in' => '09:00',
                    'new_clock_out' => '18:00',
                    'new_break_in' => [],
                    'new_break_out' => [],
                    'comment' => '',
                ],
                'error' => 'comment',
            ],
        ];

        foreach ($invalidCases as $case) {
            $response = $this->actingAs($admin)
                ->from('/admin/attendance/list')
                ->post(
                    '/attendance/'.$attendance->id,
                    $case['input']
                );

            $response->assertSessionHasErrors([
                $case['error'],
            ]);

            $this->assertDatabaseHas('attendances', [
                'id' => $attendance->id,
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
                'comment' => '修正前の備考',
            ]);
        }
    }

    public function test_admin_staff_list_shows_general_users_in_id_order(): void
    {
        $admin = User::factory()->create([
            'admin_status' => 1,
            'name' => '管理者',
            'email' => 'admin@example.com',
        ]);

        $userB = User::factory()->create([
            'admin_status' => 0,
            'name' => 'スタッフB',
            'email' => 'staff-b@example.com',
        ]);

        $userA = User::factory()->create([
            'admin_status' => 0,
            'name' => 'スタッフA',
            'email' => 'staff-a@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/staff/list');

        $response->assertOk()
            ->assertViewIs('admin.staff-list')
            ->assertViewHas('users', function ($users) use (
                $admin,
                $userA,
                $userB
            ) {
                return $users->pluck('id')->all() === [
                    $userB->id,
                    $userA->id,
                ]
                    && ! $users->contains('id', $admin->id)
                    && $users->contains(
                        fn ($user) => $user->name === 'スタッフA'
                            && $user->email === 'staff-a@example.com'
                    )
                    && $users->contains(
                        fn ($user) => $user->name === 'スタッフB'
                            && $user->email === 'staff-b@example.com'
                    );
            });
    }

    public function test_general_user_cannot_access_admin_staff_list(): void
    {
        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $this->actingAs($user)
            ->get('/admin/staff/list')
            ->assertForbidden();
    }

    public function test_admin_attendance_list_calculates_multiple_breaks_and_work_time(): void
    {
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-15',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $attendance->breakTimes()->createMany([
            [
                'break_in' => '12:00:00',
                'break_out' => '12:30:00',
            ],
            [
                'break_in' => '15:00:00',
                'break_out' => '15:15:00',
            ],
        ]);

        $this->actingAs($admin)
            ->get('/admin/attendance/list?date=2026-10-15')
            ->assertOk()
            ->assertViewHas('attendanceRecords', function ($records) use (
                $attendance
            ) {
                $record = $records->firstWhere('id', $attendance->id);

                return $record !== null
                    && $record->total_break_time === '0:45'
                    && $record->total_time === '8:15';
            });
    }
}
