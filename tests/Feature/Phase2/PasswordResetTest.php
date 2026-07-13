<?php

namespace Tests\Feature\Phase2;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * PasswordResetTest — UC-C04
 */
class PasswordResetTest extends Phase2TestCase
{
    // ─────────────────────────────────────────────
    //  Forgot Password
    // ─────────────────────────────────────────────

    public function test_forgot_password_returns_success_for_existing_email(): void
    {
        $user = $this->makePatient();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $this->assertSuccessResponse($response, 200);
        $this->assertStringContainsString(
            'Password reset instructions have been sent if the account exists.',
            $response->json('message')
        );
    }

    public function test_forgot_password_returns_same_message_for_nonexistent_email(): void
    {
        // لا يكشف وجود البريد — User Enumeration Prevention
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'ghost@test.local',
        ]);

        $this->assertSuccessResponse($response, 200);
        $this->assertStringContainsString(
            'password reset link has been sent',
            $response->json('message')
        );
    }

    public function test_forgot_password_requires_valid_email_format(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'not-a-valid-email',
        ]);

        $response->assertStatus(422);
        $this->assertArrayHasKey('email', $response->json('errors'));
    }

    // ─────────────────────────────────────────────
    //  Reset Password
    // ─────────────────────────────────────────────

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user  = $this->makePatient();
        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $this->assertSuccessResponse($response, 200);
        $this->assertStringContainsString('reset successfully', $response->json('message'));

        // التحقق أن كلمة المرور تغيّرت فعلاً
        $this->assertTrue(Hash::check('NewPassword1', $user->fresh()->password));
    }

    public function test_all_tokens_revoked_after_password_reset(): void
    {
        $user     = $this->makePatient(['password' => 'OldPassword1']);
        $oldToken = $user->createToken('device_1')->plainTextToken;

        $resetToken = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => $resetToken,
            'email'                 => $user->email,
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        // التوكن القديم يجب أن يُرفَض
        $response = $this->withToken($oldToken)->getJson('/api/v1/auth/me');
        $this->assertErrorResponse($response, 401);
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = $this->makePatient();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => 'completely-fake-token',
            'email'                 => $user->email,
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $this->assertErrorResponse($response, 422);
    }

    public function test_reset_password_requires_all_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', []);

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $this->assertArrayHasKey('token', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    // ─────────────────────────────────────────────
    //  Change Password [auth:sanctum]
    // ─────────────────────────────────────────────

    public function test_authenticated_user_can_change_password(): void
    {
        $user  = $this->makePatient(['password' => 'OldPassword1']);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password'      => 'OldPassword1',
                'password'              => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ]);

        $this->assertSuccessResponse($response, 200);
        $this->assertTrue(Hash::check('NewPassword1', $user->fresh()->password));
    }

    public function test_change_password_fails_with_wrong_current_password(): void
    {
        $user  = $this->makePatient(['password' => 'OldPassword1']);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password'      => 'WrongPassword1',
                'password'              => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
            ]);

        // AuthorizationException → 403
        $this->assertErrorResponse($response, 403);
    }

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/change-password', [
            'current_password'      => 'OldPassword1',
            'password'              => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $this->assertErrorResponse($response, 401);
    }
}
