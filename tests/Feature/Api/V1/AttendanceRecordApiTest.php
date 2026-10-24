<?php

namespace Tests\Feature\Api\V1;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRecordApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_attendance_records(): void
    {
        $user = User::factory()->create();

        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->getJson('/api/v1/attendance-records');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'date',
                        'clock_in',
                        'clock_out',
                        'total_time',
                        'total_break_time',
                        'comment',
                    ],
                ],
                'links',
                'meta',
            ])
            ->assertJsonPath('data.0.user_id', $user->id)
            ->assertJsonPath('data.0.date', '2026-10-20')
            ->assertJsonPath('data.0.total_time', '9:00')
            ->assertJsonPath('data.0.total_break_time', '0:00');
    }

    public function test_can_get_attendance_record_detail(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $attendance->breakTimes()->create([
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $response = $this->getJson(
            "/api/v1/attendance-records/{$attendance->id}"
        );

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'user_id',
                    'date',
                    'clock_in',
                    'clock_out',
                    'total_time',
                    'total_break_time',
                    'comment',
                    'user' => ['id', 'name'],
                    'breaks' => ['*' => ['id', 'attendance_id', 'break_in', 'break_out']],
                    'applications',
                ],
            ])
            ->assertJsonPath('data.id', $attendance->id)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.total_time', '8:00')
            ->assertJsonPath('data.total_break_time', '1:00')
            ->assertJsonCount(1, 'data.breaks');
    }

    public function test_returns_404_for_nonexistent_attendance_record(): void
    {
        $response = $this->getJson('/api/v1/attendance-records/999999');

        $response->assertNotFound()
            ->assertExactJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }

    public function test_authenticated_user_can_create_attendance_record(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance-records', [
                'date' => '2026-10-21',
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
                'comment' => 'APIから登録',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.date', '2026-10-21')
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.clock_out', '18:00:00')
            ->assertJsonPath('data.comment', 'APIから登録');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => '2026-10-21',
            'comment' => 'APIから登録',
        ]);
    }

    public function test_guest_cannot_create_attendance_record(): void
    {
        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-10-21',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response->assertUnauthorized();

        $this->assertDatabaseMissing('attendances', [
            'date' => '2026-10-21',
        ]);
    }

    public function test_guest_cannot_update_attendance_record(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->putJson(
            "/api/v1/attendance-records/{$attendance->id}",
            ['comment' => '更新テスト'],
        );

        $response->assertUnauthorized();
    }

    public function test_guest_cannot_patch_attendance_record(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->patchJson(
            "/api/v1/attendance-records/{$attendance->id}",
            ['comment' => '更新テスト']
        );

        $response->assertUnauthorized();
    }

    public function test_guest_cannot_delete_attendance_record(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/attendance-records/{$attendance->id}"
        );

        $response->assertUnauthorized();

        $this->assertDatabaseHas('attendances', ['id' => $attendance->id]);
    }

    public function test_attendance_record_belongs_to_authenticated_user(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance-records', [
                'user_id' => $anotherUser->id,
                'date' => '2026-10-21',
                'clock_in' => '09:00:00',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $user->id);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $user->id,
            'date' => '2026-10-21',
        ]);

        $this->assertDatabaseMissing('attendances', [
            'user_id' => $anotherUser->id,
            'date' => '2026-10-21',
        ]);
    }

    public function test_cannot_create_duplicate_attendance_record_for_same_user_and_date(): void
    {
        $user = User::factory()->create();

        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-21',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance-records', [
                'date' => '2026-10-21',
                'clock_in' => '10:00:00',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_create_attendance_record_rejects_invalid_input(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/attendance-records', [
                'date' => '2026/10/21',
                'clock_in' => '9:00',
                'clock_out' => '08:00:00',
                'comment' => str_repeat('あ', 256),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date',
                'clock_in',
                'clock_out',
                'comment',
            ]);

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_attendance_owner_can_update_attendance_record(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '更新前',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/attendance-records/{$attendance->id}", [
                'date' => '2026-10-20',
                'clock_in' => '09:30:00',
                'clock_out' => '18:00:00',
                'comment' => '更新後',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $attendance->id)
            ->assertJsonPath('data.clock_in', '09:30:00')
            ->assertJsonPath('data.comment', '更新後');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '09:30:00',
            'comment' => '更新後',
        ]);
    }

    public function test_user_cannot_update_another_users_attendance_record(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $anotherUser->id,
            'date' => '2026-10-20',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '変更前',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/attendance-records/{$attendance->id}", [
                'date' => '2026-10-20',
                'clock_in' => '10:00:00',
                'clock_out' => '18:00:00',
                'comment' => '不正な変更',
            ]);

        $response->assertForbidden()->assertExactJson(['error' => 'この操作を実行する権限がありません。']);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $anotherUser->id,
            'clock_in' => '09:00:00',
            'comment' => '変更前',
        ]);
    }

    public function test_admin_can_update_another_users_attendance_record(): void
    {
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $user = User::factory()->create([
            'admin_status' => false,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '更新前',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->putJson("/api/v1/attendance-records/{$attendance->id}", [
                'date' => '2026-10-20',
                'clock_in' => '10:00:00',
                'clock_out' => '19:00:00',
                'comment' => '管理者が更新',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.clock_in', '10:00:00')
            ->assertJsonPath('data.comment', '管理者が更新');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $user->id,
            'clock_in' => '10:00:00',
            'comment' => '管理者が更新',
        ]);
    }

    public function test_patch_updates_only_provided_fields(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '変更前',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/attendance-records/{$attendance->id}", [
                'comment' => 'コメントだけ変更',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.comment', 'コメントだけ変更')
            ->assertJsonPath('data.clock_in', '09:00:00')
            ->assertJsonPath('data.clock_out', '18:00:00')
            ->assertJsonPath('data.date', '2026-10-20');

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'comment' => 'コメントだけ変更',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    public function test_patch_rejects_clock_out_before_existing_clock_in(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/attendance-records/{$attendance->id}", [
                'clock_out' => '08:00:00',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['clock_out']);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    public function test_attendance_owner_can_delete_attendance_record(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/attendance-records/{$attendance->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('attendances', [
            'id' => $attendance->id,
        ]);
    }

    public function test_user_cannot_delete_another_users_attendance_record(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this->actingAs($otherUser, 'sanctum')
            ->deleteJson("/api/v1/attendance-records/{$attendance->id}");

        $response->assertForbidden()->assertExactJson(['error' => 'この操作を実行する権限がありません。']);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_admin_can_delete_another_users_attendance_record(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create([
            'admin_status' => true,
        ]);

        $attendance = Attendance::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/v1/attendance-records/{$attendance->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('attendances', [
            'id' => $attendance->id,
        ]);
    }

    public function test_delete_returns_404_for_nonexistent_attendance_record(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/attendance-records/999999');

        $response->assertNotFound()
            ->assertExactJson([
                'error' => '勤怠情報が見つかりませんでした。',
            ]);
    }

    public function test_can_filter_attendance_records_by_user_id(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $attendace = Attendance::factory()->create([
            'user_id' => $user1->id,
            'date' => '2026-10-20',
        ]);

        $attendace = Attendance::factory()->create([
            'user_id' => $user2->id,
            'date' => '2026-10-21',
        ]);

        $response = $this->getJson("/api/v1/attendance-records?user_id={$user1->id}");

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user_id', $user1->id);
    }

    public function test_can_filter_attendance_records_by_date(): void
    {
        $user = User::factory()->create();

        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
        ]);

        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-21',
        ]);

        $response = $this->getJson(
            '/api/v1/attendance-records?date=2026-10-20'
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2026-10-20');
    }

    public function test_can_filter_attendance_records_by_month(): void
    {
        $user = User::factory()->create();

        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
        ]);

        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-11-01',
        ]);

        $response = $this->getJson(
            '/api/v1/attendance-records?month=2026-10'
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2026-10-20');
    }

    public function test_can_paginate_attendance_records(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 3) as $day) {
            Attendance::factory()->create([
                'user_id' => $user->id,
                'date' => sprintf('2026-10-%02d', $day),
            ]);
        }

        $response = $this->getJson(
            '/api/v1/attendance-records?per_page=2&page=2'
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2026-10-01')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_update_rejects_clock_out_before_clock_in(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/attendance-records/{$attendance->id}", [
                'date' => '2026-10-20',
                'clock_in' => '09:00:00',
                'clock_out' => '08:00:00',
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['clock_out']);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    public function test_update_rejects_duplicate_date_for_same_user(): void
    {
        $user = User::factory()->create();

        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $targetAttendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-21',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/attendance-records/{$targetAttendance->id}", [
                'date' => '2026-10-20',
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
            ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['date']);

        $this->assertDatabaseHas('attendances', [
            'id' => $targetAttendance->id,
            'date' => '2026-10-21',
        ]);
    }

    public function test_update_rejects_comment_over_255_characters(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '変更前のコメント',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/attendance-records/{$attendance->id}", [
                'date' => '2026-10-20',
                'clock_in' => '09:00:00',
                'clock_out' => '18:00:00',
                'comment' => str_repeat('あ', 256),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['comment']);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'comment' => '変更前のコメント',
        ]);
    }

    public function test_update_rejects_invalid_date_and_time_formats(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson("/api/v1/attendance-records/{$attendance->id}", [
                'date' => '2026/10/20',
                'clock_in' => '9:00',
                'clock_out' => '18:00:00',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'clock_in']);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'date' => '2026-10-20',
            'clock_in' => '09:00:00',
        ]);
    }

    public function test_patch_rejects_comment_over_255_characters(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => '変更前のコメント',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/attendance-records/{$attendance->id}", [
                'comment' => str_repeat('あ', 256),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['comment']);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'comment' => '変更前のコメント',
        ]);
    }

    public function test_attendance_record_detail_includes_applications(): void
    {
        $user = User::factory()->create();

        $attendance = Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-10-20',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $application = $attendance->attendanceCorrectionRequests()->create([
            'new_clock_in' => '08:30:00',
            'new_clock_out' => '17:30:00',
            'comment' => '打刻時間を修正します。',
            'status' => 'pending',
        ]);

        $response = $this->getJson(
            "/api/v1/attendance-records/{$attendance->id}",
        );

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'applications' => [
                        '*' => [
                            'id',
                            'attendance_id',
                            'new_clock_in',
                            'new_clock_out',
                            'comment',
                            'status',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.applications.0.id', $application->id)
            ->assertJsonPath('data.applications.0.attendance_id', $attendance->id)
            ->assertJsonPath('data.applications.0.new_clock_in', '08:30:00')
            ->assertJsonPath('data.applications.0.new_clock_out', '17:30:00')
            ->assertJsonPath('data.applications.0.comment', '打刻時間を修正します。')
            ->assertJsonPath('data.applications.0.status', 'pending');
    }
}
