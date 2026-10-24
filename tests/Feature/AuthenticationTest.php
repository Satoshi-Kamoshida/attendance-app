<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_validation_and_success(): void
    {
        $invalidCases = [
            [
                'input' => [
                    'email' => 'test@example.com',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                ],
                'error' => 'name',
            ],
            [
                'input' => [
                    'name' => 'テストユーザー',
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                ],
                'error' => 'email',
            ],
            [
                'input' => [
                    'name' => 'テストユーザー',
                    'email' => 'test@example.com',
                    'password' => 'short',
                    'password_confirmation' => 'short',
                ],
                'error' => 'password',
            ],
            [
                'input' => [
                    'name' => 'テストユーザー',
                    'email' => 'test@example.com',
                    'password' => 'password123',
                    'password_confirmation' => 'different123',
                ],
                'error' => 'password',
            ],
            [
                'input' => [
                    'name' => 'テストユーザー',
                    'email' => 'test@example.com',
                    'password_confirmation' => 'password123',
                ],
                'error' => 'password',
            ],
        ];

        foreach ($invalidCases as $case) {
            $response = $this->post(
                '/register',
                $case['input']
            );

            $response->assertSessionHasErrors([
                $case['error'],
            ]);

            $errorMessages = $response->getSession()->get('errors')->getBag('default');

            $expectedMessages = [
                'name' => 'お名前を入力してください',
                'email' => 'メールアドレスを入力してください',
                'password_required' => 'パスワードを入力してください',
                'password_min' => 'パスワードは8文字以上で入力してください',
                'password_confirmed' => 'パスワードと一致しません',
            ];

            if ($case['error'] === 'name' || $case['error'] === 'email') {
                $this->assertSame(
                    $expectedMessages[$case['error']],
                    $errorMessages->first($case['error'])
                );
            }

            if (
                $case['error'] === 'password'
                && ! isset($case['input']['password'])
            ) {
                $this->assertSame(
                    $expectedMessages['password_required'],
                    $errorMessages->first('password')
                );
            }

            if (
                isset($case['input']['password'])
                && strlen($case['input']['password']) < 8
            ) {
                $this->assertSame(
                    $expectedMessages['password_min'],
                    $errorMessages->first('password')
                );
            }

            if (
                isset($case['input']['password_confirmation'])
                && isset($case['input']['password'])
                && $case['input']['password'] !== $case['input']['password_confirmation']
            ) {
                $this->assertSame(
                    $expectedMessages['password_confirmed'],
                    $errorMessages->first('password')
                );
            }

            $this->assertDatabaseCount('users', 0);
        }

        $response = $this->post('/register', [
            'name' => '登録テストユーザー',
            'email' => 'registration@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertDatabaseHas('users', [
            'name' => '登録テストユーザー',
            'email' => 'registration@example.com',
            'admin_status' => 0,
        ]);

        $this->assertAuthenticated();
    }

    public function test_user_and_admin_login_validation_and_authentication(): void
    {
        $user = User::factory()->create([
            'email' => 'user-login@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => 0,
        ]);

        $admin = User::factory()->create([
            'email' => 'admin-login@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => 1,
        ]);

        $this->post('/login', [
            'password' => 'password123',
        ])->assertSessionHasErrors(['email']);

        $this->post('/login', [
            'email' => $user->email,
        ])->assertSessionHasErrors(['password']);

        $this->post('/admin/login', [
            'password' => 'password123',
        ])->assertSessionHasErrors(['email']);

        $this->post('/admin/login', [
            'email' => $admin->email,
        ])->assertSessionHasErrors(['password']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertSessionHasErrors([
                'email' => 'ログイン情報が登録されていません',
            ]);

        $this->assertGuest();

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])
            ->assertSessionHasErrors([
                'email' => 'ログイン情報が登録されていません',
            ]);

        $this->assertGuest();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect('/attendance');

        $this->assertAuthenticatedAs($user);

        auth()->logout();
        $this->get('/login');

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ])->assertRedirect('/admin/attendance/list');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_general_user_cannot_login_through_admin_login(): void
    {
        $user = User::factory()->create([
            'email' => 'ordinary@example.com',
            'password' => Hash::make('password123'),
            'admin_status' => 0,
        ]);

        $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $this->assertGuest();
    }

    public function test_user_can_verify_email(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        $response->assertRedirect('/attendance?verified=1');

        $this->assertTrue($user->fresh()->email_verified_at !== null, 'メール認証後、email_verified_atが更新されていること');
    }

    public function test_unverified_user_can_resend_verification_email(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)
            ->post(route('verification.send'));

        $response->assertRedirect();

        Notification::assertSentTo(
            $user,
            VerifyEmail::class
        );
    }

    public function test_unverified_user_cannot_access_attendance_page(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_user_can_access_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));

        $response->assertOk();
    }
}
