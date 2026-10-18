<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BreakTime;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ゲストはマイ勤怠レポートにアクセスできない。
     */
    public function test_guest_is_redirected_from_attendance_report(): void
    {
        $response = $this->get('/attendance/report');

        $response->assertRedirect('/login');
    }

    /**
     * マイ勤怠レポートの集計値が正しく表示される。
     */
    public function test_user_can_view_attendance_report_with_correct_statistics(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 12:00:00'));

        try {
            $user = User::factory()->create([
                'email_verified_at' => now(),
            ]);

            $attendance = Attendance::factory()->create([
                'user_id' => $user->id,
                'date' => '2026-10-05',
                'clock_in' => '09:00:00',
                'clock_out' => '20:00:00',
            ]);

            BreakTime::factory()->create([
                'attendance_id' => $attendance->id,
                'break_in' => '12:00:00',
                'break_out' => '13:00:00',
            ]);

            $response = $this->actingAs($user)
                ->get('/attendance/report');

            $response->assertOk()
                ->assertViewHas('summary', function (array $summary) {
                    return $summary['total_work_minutes'] === 600
                        && $summary['total_overtime_minutes'] === 120
                        && $summary['avg_work_minutes'] === 600;
                })
                ->assertViewHas('monthlyTrend', function ($monthlyTrend) {
                    $currentMonth = $monthlyTrend->last();

                    return $monthlyTrend->count() === 6
                        && $currentMonth['work_minutes'] === 600
                        && $currentMonth['overtime_minutes'] === 120;
                })
                ->assertViewHas('anomalies', function (array $anomalies) {
                    return $anomalies['late_count'] === 0
                        && $anomalies['early_leave_count'] === 0
                        && $anomalies['long_work_count'] === 0;
                });
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 勤怠データがないユーザーの集計値は0になる。
     */
    public function test_user_without_attendance_records_sees_zero_statistics(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get('/attendance/report');

        $response->assertOk()
            ->assertViewHas('summary', function (array $summary) {
                return $summary['total_work_minutes'] === 0
                    && $summary['total_overtime_minutes'] === 0
                    && $summary['avg_work_minutes'] === 0;
            })
            ->assertViewHas('monthlyTrend', function ($monthlyTrend) {
                return $monthlyTrend->count() === 6
                    && $monthlyTrend->every(function (array $month) {
                        return $month['work_minutes'] === 0
                            && $month['overtime_minutes'] === 0;
                    });
            })
            ->assertViewHas('anomalies', function (array $anomalies) {
                return $anomalies['late_count'] === 0
                    && $anomalies['early_leave_count'] === 0
                    && $anomalies['long_work_count'] === 0;
            });
    }

    /**
     * 今月の遅刻・早退・長時間労働が正しく集計される。
     */
    public function test_attendance_report_counts_monthly_anomalies_correctly(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 12:00:00'));

        try {
            $user = User::factory()->create([
                'email_verified_at' => now(),
            ]);

            Attendance::factory()->create([
                'user_id' => $user->id,
                'date' => '2026-10-05',
                'clock_in' => '09:30:00',
                'clock_out' => '18:00:00',
            ]);

            Attendance::factory()->create([
                'user_id' => $user->id,
                'date' => '2026-10-06',
                'clock_in' => '09:00:00',
                'clock_out' => '17:00:00',
            ]);

            $longWorkAttendance = Attendance::factory()->create([
                'user_id' => $user->id,
                'date' => '2026-10-07',
                'clock_in' => '08:00:00',
                'clock_out' => '21:00:00',
            ]);

            BreakTime::factory()->create([
                'attendance_id' => $longWorkAttendance->id,
                'break_in' => '12:00:00',
                'break_out' => '13:00:00',
            ]);

            $response = $this->actingAs($user)
                ->get('/attendance/report');

            $response->assertOk()
                ->assertViewHas('anomalies', function (array $anomalies) {
                    return $anomalies['late_count'] === 1
                        && $anomalies['early_leave_count'] === 1
                        && $anomalies['long_work_count'] === 1;
                });
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * 他のユーザーの勤怠はレポートに集計されない。
     */
    public function test_attendance_report_does_not_include_other_users_records(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-18 12:00:00'));

        try {
            $user = User::factory()->create([
                'email_verified_at' => now(),
            ]);

            $otherUser = User::factory()->create([
                'email_verified_at' => now(),
            ]);

            Attendance::factory()->create([
                'user_id' => $user->id,
                'date' => '2026-10-05',
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]);

            Attendance::factory()->create([
                'user_id' => $otherUser->id,
                'date' => '2026-10-06',
                'clock_in' => '09:00:00',
                'clock_out' => '20:00:00',
            ]);

            $response = $this->actingAs($user)
                ->get('/attendance/report');

            $response->assertOk()
                ->assertViewHas('summary', function (array $summary) {
                    return $summary['total_work_minutes'] === 540
                        && $summary['total_overtime_minutes'] === 60
                        && $summary['avg_work_minutes'] === 540;
                });
        } finally {
            Carbon::setTestNow();
        }
    }
}
