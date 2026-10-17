<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_attendance_list(): void
    {
        $response = $this->get('/attendance/list');

        $response->assertRedirect('/login');
    }

    public function test_user_can_access_own_attendance_list(): void
    {
        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/list');

        $response->assertOk();
    }

    public function test_user_cannot_view_another_users_attendance(): void
    {
        $userA = User::factory()->create([
            'admin_status' => 0,
        ]);

        $userB = User::factory()->create([
            'admin_status' => 0,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $userB->id,
        ]);

        $response = $this->actingAs($userA)
            ->get('/attendance/'.$attendance->id);

        $response->assertNotFound();
    }

    public function test_user_can_clock_in(): void
    {
        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $this->travelTo(Carbon::parse('2026-10-14 09:00:00'));

        $response = $this->actingAs($user)
            ->post('/attendance', [
                'action' => 'clock_in',
            ]);

        $response->assertRedirect('/attendance');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
        ]);
    }

    public function test_user_can_start_break(): void
    {
        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $this->travelTo(Carbon::parse('2026-10-14 12:00:00'));

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        $response = $this->actingAs($user)
            ->post('/attendance', [
                'action' => 'break_in',
            ]);

        $response->assertRedirect('/attendance');

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => null,
        ]);
    }

    public function test_user_can_end_break(): void
    {
        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $this->travelTo(Carbon::parse('2026-10-14 12:45:00'));

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        $breakTime = $attendance->breakTimes()->create([
            'break_in' => '12:00:00',
            'break_out' => null,
        ]);

        $response = $this->actingAs($user)
            ->post('/attendance', [
                'action' => 'break_out',
            ]);

        $response->assertRedirect('/attendance');

        $this->assertDatabaseHas('break_times', [
            'id' => $breakTime->id,
            'attendance_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '12:45:00',
        ]);
    }

    public function test_user_can_take_multiple_breaks_in_one_day(): void
    {
        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        $this->actingAs($user);

        $this->travelTo(Carbon::parse('2026-10-14 12:00:00'));

        $this->post('/attendance', [
            'action' => 'break_in',
        ])->assertRedirect('/attendance');

        $this->travelTo(Carbon::parse('2026-10-14 12:45:00'));

        $this->post('/attendance', [
            'action' => 'break_out',
        ])->assertRedirect('/attendance');

        $this->travelTo(Carbon::parse('2026-10-14 15:00:00'));

        $this->post('/attendance', [
            'action' => 'break_in',
        ])->assertRedirect('/attendance');

        $this->travelTo(Carbon::parse('2026-10-14 15:15:00'));

        $this->post('/attendance', [
            'action' => 'break_out',
        ])->assertRedirect('/attendance');

        $this->assertDatabaseCount('break_times', 2);

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'break_in' => '12:00:00',
            'break_out' => '12:45:00',
        ]);

        $this->assertDatabaseHas('break_times', [
            'attendance_id' => $attendance->id,
            'break_in' => '15:00:00',
            'break_out' => '15:15:00',
        ]);

        $this->get('/attendance')
            ->assertOk()
            ->assertViewHas('user', function ($viewUser) {
                return $viewUser->attendance_status === '出勤中';
            });
    }

    public function test_user_can_clock_out(): void
    {
        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $this->travelTo(Carbon::parse('2026-10-14 17:30:00'));

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        $response = $this->actingAs($user)
            ->post('/attendance', [
                'action' => 'clock_out',
            ]);

        $response->assertRedirect('/attendance');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $user->id,
            'clock_in' => '09:00:00',
            'clock_out' => '17:30:00',
        ]);

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_attendance_list_shows_only_own_records_for_selected_month(): void
    {
        $userA = User::factory()->create(['admin_status' => 0]);
        $userB = User::factory()->create(['admin_status' => 0]);

        $targetAttendance = Attendance::factory()->create([
            'user_id' => $userA->id,
            'date' => '2026-10-14',
        ]);

        Attendance::factory()->create([
            'user_id' => $userA->id,
            'date' => '2026-09-14',
        ]);

        Attendance::factory()->create([
            'user_id' => $userB->id,
            'date' => '2026-10-15',
        ]);

        $response = $this->actingAs($userA)
            ->get('/attendance/list?date=2026-10');

        $response->assertOk();

        $response->assertViewHas('previousMonth', '2026-09');

        $response->assertViewHas('date', function ($date) {
            return $date->format('Y-m') === '2026-10';
        });

        $response->assertViewHas('nextMonth', '2026-11');

        $response->assertViewHas(
            'formattedAttendanceRecords',
            function ($records) use ($targetAttendance) {
                $ids = collect($records)
                    ->pluck('id')
                    ->all();

                return $ids === [$targetAttendance->id];
            }
        );
    }

    public function test_user_can_view_own_attendance_details(): void
    {
        $user = User::factory()->create([
            'name' => 'テストユーザー',
            'admin_status' => 0,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $attendance->breakTimes()->create([
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $attendance->breakTimes()->create([
            'break_in' => '15:00:00',
            'break_out' => '15:15:00',
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/'.$attendance->id);

        $response->assertOk();

        $response->assertViewHas('user', function ($viewUser) use ($user) {
            return $viewUser->id === $user->id
                && $viewUser->name === 'テストユーザー';
        });

        $response->assertViewHas('data', function ($data) use ($attendance) {
            return $data['id'] === $attendance->id
                && $data['year'] === '2026年'
                && $data['date'] === '10月14日'
                && $data['clock_in'] === '09:00'
                && $data['clock_out'] === '18:00'
                && $data['comment'] === ''
                && count($data['breaks']) === 2
                && $data['breaks'][0]['break_in'] === '12:00'
                && $data['breaks'][0]['break_out'] === '13:00'
                && $data['breaks'][1]['break_in'] === '15:00'
                && $data['breaks'][1]['break_out'] === '15:15';
        });
    }

    public function test_attendance_list_defaults_to_current_month_and_sums_multiple_breaks(): void
    {

        $this->travelTo(
            Carbon::parse('2026-10-14 20:00:00')
        );

        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $attendance->breakTimes()->create([
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $attendance->breakTimes()->create([
            'break_in' => '15:00:00',
            'break_out' => '15:15:00',
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/list');

        $response->assertOk();

        $response->assertViewHas('date', function ($date) {
            return $date->format('Y-m') === '2026-10';
        });

        $response->assertViewHas('previousMonth', '2026-09');
        $response->assertViewHas('nextMonth', '2026-11');

        $response->assertViewHas(
            'formattedAttendanceRecords',
            function ($records) use ($attendance) {
                $record = collect($records)->firstWhere(
                    'id',
                    $attendance->id
                );

                return $record !== null
                    && $record['total_break_time'] === '1:15'
                    && $record['total_time'] === '7:45';
            }
        );
    }

    public function test_attendance_page_shows_current_datetime_and_status(): void
    {
        $this->travelTo(
            Carbon::parse('2026-10-15 09:30:00')
        );

        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertOk();

        $response->assertViewHas('formattedDate', '2026/10/15');
        $response->assertViewHas('formattedTime', '09:30');

        $response->assertViewHas('user', function ($viewUser) {
            return $viewUser->attendance_status === '勤務外';
        });

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-15',
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        $this->get('/attendance')
            ->assertViewHas('user', function ($viewUser) {
                return $viewUser->attendance_status === '出勤中';
            })
            ->assertSee('休憩入')
            ->assertDontSee('休憩戻');

        $attendance->breakTimes()->create([
            'break_in' => '12:00:00',
            'break_out' => null,
        ]);

        $this->get('/attendance')
            ->assertViewHas('user', function ($viewUser) {
                return $viewUser->attendance_status === '休憩中';
            })
            ->assertSee('休憩戻')
            ->assertDontSee('休憩入');

        $attendance->update([
            'clock_out' => '18:00:00',
        ]);

        $this->get('/attendance')
            ->assertViewHas('user', function ($viewUser) {
                return $viewUser->attendance_status === '退勤済';
            });
    }

    public function test_clock_in_does_not_create_duplicate_attendance(): void
    {
        $this->travelTo(
            Carbon::parse('2026-10-15 09:00:00')
        );

        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-15',
            'clock_in' => '08:55:00',
            'clock_out' => null,
        ]);

        $this->actingAs($user)->post('/attendance', [
            'action' => 'clock_in',
        ])->assertRedirect('/attendance');

        $this->assertDatabaseCount('attendances', 1);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '08:55:00',
        ]);
    }

    public function test_admin_can_view_correct_attendance_detail(): void
    {
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $user = User::factory()->create([
            'name' => '詳細確認スタッフ',
            'admin_status' => 0,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '確認用の備考',
        ]);

        $attendance->breakTimes()->create([
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $this->actingAs($admin)
            ->get('/admin/attendance/'.$attendance->id)
            ->assertOk()
            ->assertViewIs('admin.admin-detail')

            ->assertViewHas('attendanceRecord', function ($record) use (
                $attendance
            ) {
                return $record['id'] === $attendance->id
                    && $record['year'] === '2026年'
                    && $record['date'] === '10月14日'
                    && $record['clock_in'] === '09:00'
                    && $record['clock_out'] === '18:00'
                    && $record['comment'] === '確認用の備考'
                    && count($record['breaks']) === 1
                    && $record['breaks'][0]['break_in'] === '12:00'
                    && $record['breaks'][0]['break_out'] === '13:00';
            });
    }

    public function test_user_attendance_detail_contains_correct_display_data(): void
    {
        $user = User::factory()->create([
            'name' => '詳細表示テストスタッフ',
            'admin_status' => 0,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $attendance->breakTimes()->create([
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $this->actingAs($user)
            ->get('/attendance/'.$attendance->id)
            ->assertOk()
            ->assertViewHas('user', function ($viewUser) use ($user) {
                return $viewUser->id === $user->id
                    && $viewUser->name === '詳細表示テストスタッフ';
            })
            ->assertViewHas('data', function ($data) use ($attendance) {
                return $data['id'] === $attendance->id
                && $data['year'] === '2026年'
                && $data['date'] === '10月14日'
                && $data['clock_in'] === '09:00'
                && $data['clock_out'] === '18:00'
                && count($data['breaks']) === 1
                && $data['breaks'][0]['break_in'] === '12:00'
                && $data['breaks'][0]['break_out'] === '13:00';
            });
    }
}
