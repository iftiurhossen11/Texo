<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TexoResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_is_sent_only_when_smtp_is_configured_and_does_not_disclose_account_existence(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        NotificationFacade::fake();
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.example.com',
            'mail.from.address' => 'no-reply@texo.example',
            'app.frontend_url' => 'https://texo.example',
        ]);
        $user = User::query()->create([
            'name' => 'Texo User',
            'email' => 'user@example.com',
            'password' => 'Texo-Strong-Pass-123',
            'role' => 'employee',
        ]);

        $response = $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for this email, a password reset link will be sent.');

        $notification = null;
        NotificationFacade::assertSentTo($user, TexoResetPassword::class, function (TexoResetPassword $sent) use (&$notification): bool {
            $notification = $sent;

            return true;
        });
        $this->assertStringContainsString('https://texo.example/?password-reset=1&token=', $notification->toMail($user)->actionUrl);

        $this->postJson('/api/forgot-password', ['email' => 'missing@example.com'])
            ->assertOk()
            ->assertExactJson($response->json());
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_password_reset_changes_password_and_revokes_existing_access_tokens(): void
    {
        $user = User::query()->create([
            'name' => 'Texo User',
            'email' => 'user@example.com',
            'password' => 'Texo-Old-Pass-123!',
            'role' => 'employee',
        ]);
        $user->createToken('existing-session');
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => strtoupper($user->email),
            'password' => 'Texo-New-Password-456!',
            'password_confirmation' => 'Texo-New-Password-456!',
        ])->assertOk()
            ->assertJsonPath('message', 'Your password has been reset.');

        $this->assertTrue(Hash::check('Texo-New-Password-456!', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_reset_rejects_weak_passwords_and_invalid_tokens(): void
    {
        $user = User::query()->create([
            'name' => 'Texo User',
            'email' => 'user@example.com',
            'password' => 'Texo-Old-Pass-123!',
            'role' => 'employee',
        ]);
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'weak-password',
            'password_confirmation' => 'weak-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->postJson('/api/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'Texo-New-Password-456!',
            'password_confirmation' => 'Texo-New-Password-456!',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_does_not_send_reset_tokens_to_log_mailer(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        NotificationFacade::fake();
        config([
            'mail.default' => 'log',
            'mail.mailers.smtp.host' => 'smtp.example.com',
            'mail.from.address' => 'no-reply@texo.example',
        ]);
        $user = User::query()->create([
            'name' => 'Texo User',
            'email' => 'user@example.com',
            'password' => 'Texo-Strong-Pass-123',
            'role' => 'employee',
        ]);

        $this->postJson('/api/forgot-password', ['email' => $user->email])
            ->assertServiceUnavailable();

        NotificationFacade::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }
}
