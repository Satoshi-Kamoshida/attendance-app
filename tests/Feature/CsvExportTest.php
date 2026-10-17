<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_export_attendance_csv(): void
    {
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-14',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-09-14',
            'clock_in' => '09:00:00',
            'clock_out' => '17:00:00',
        ]);

        $response = $this->actingAs($admin)->post('/export', [
            'user_id' => $user->id,
            'year_month' => '2026-10',
        ]);

        $response->assertOk();
        $response->assertDownload(
            "attendance_{$user->id}_2026-10.csv"
        );

        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('日付', $content);
        $this->assertStringContainsString('2026/10/14', $content);
        $this->assertStringNotContainsString('2026/09/14', $content);
    }

    public function test_general_user_cannot_export_attendance_csv(): void
    {
        $user = User::factory()->create([
            'admin_status' => 0,
        ]);

        $staff = User::factory()->create([
            'admin_status' => 0,
        ]);

        $response = $this->actingAs($user)->post('/export', [
            'user_id' => $staff->id,
            'year_month' => '2026-10',
        ]);

        $response->assertForbidden();
    }

    public function test_csv_export_rejects_invalid_input(): void
    {
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $this->actingAs($admin)
            ->post('/export', [
                'user_id' => '',
                'year_month' => '2026-13',
            ])
            ->assertSessionHasErrors([
                'user_id',
                'year_month',
            ]);
    }

    public function test_csv_export_rejects_admin_as_staff_target(): void
    {
        $admin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $targetAdmin = User::factory()->create([
            'admin_status' => 1,
        ]);

        $this->actingAs($admin)
            ->post('/export', [
                'user_id' => $targetAdmin->id,
                'year_month' => '2026-10',
            ])
            ->assertNotFound();
    }
}
