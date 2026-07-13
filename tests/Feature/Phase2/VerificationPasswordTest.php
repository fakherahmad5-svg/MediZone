<?php

namespace Tests\Feature\Phase2;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * VerificationPasswordTest
 *
 * اختبارات مُدمجة للـ email verification و password flows
 * من منظور "حالة المستخدم" — اختبارات integration أكثر من unit.
 */
class VerificationPasswordTest extends Phase2TestCase
{
    // ─────────────────────────────────────────────
    //  Email Verified State
    // ─────────────────────────────────────────────

    public function test_verified_user_has_email_verified_true(): void
    {
        $user  = $this->makePatient(); // email_verified_at = now()
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $this->assertSuccessResponse($response, 200);
        $this->assertTrue($response->json('data.email_verified'));
    }

    public function test_unverified_user_has_email_verified_false(): void
    {
        $user  = $this->makePatient(['email_verified_at' => null]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $this->assertSuccessResponse($response, 200);
        $this->assertFalse($response->json('data.email_verified'));
    }

    // ─────────────────────────────────────────────
    //  Email Verification Flow
    // ─────────────────────────────────────────────

    public function test_user_can_verify_email_with_valid_hash(): void
    {
        $user  = $this->makePatient(['email_verified_at' => null]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/auth/email/verify', [
            'hash' => sha1($user->email),
        ]);

        $this->assertSuccessResponse($response, 200);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_email_verification_fails_with_invalid_hash(): void
    {
        $user  = $this->makePatient(['email_verified_at' => null]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/auth/email/verify', [
            'hash' => 'invalid-hash-value',
        ]);

        $this->assertErrorResponse($response, 422);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_already_verified_email_returns_business_exception(): void
    {
        $user  = $this->makePatient(); // already verified
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/auth/email/verify', [
            'hash' => sha1($user->email),
        ]);

        $this->assertErrorResponse($response, 422);
        $this->assertStringContainsString('already verified', $response->json('message'));
    }

    public function test_resend_verification_email_succeeds_for_unverified_user(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $user  = $this->makePatient(['email_verified_at' => null]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/auth/email/resend');

        $this->assertSuccessResponse($response, 200);
        \Illuminate\Support\Facades\Notification::assertSentTo(
            $user,
            \Illuminate\Auth\Notifications\VerifyEmail::class
        );
    }

    public function test_resend_verification_fails_if_already_verified(): void
    {
        $user  = $this->makePatient(); // verified
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/auth/email/resend');

        $this->assertErrorResponse($response, 422);
    }

    public function test_email_verification_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/email/verify', [
            'hash' => 'anyhash',
        ]);

        $this->assertErrorResponse($response, 401);
    }

    public function test_verified_middleware_blocks_unverified_users_with_403(): void
    {
        $user = $this->makePatient(['email_verified_at' => null]);

        $middleware = new \App\Core\Http\Middleware\EnsureEmailIsVerified();
        $request    = \Illuminate\Http\Request::create('/api/v1/protected', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle(
            $request,
            fn ($req) => response()->json(['success' => true], 200)
        );

        $this->assertEquals(403, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertEquals('EMAIL_NOT_VERIFIED', $body['code']);
    }

    // ─────────────────────────────────────────────
    //  Forgot / Reset Password Flow
    // ─────────────────────────────────────────────

    public function test_forgot_password_always_returns_success_even_for_unknown_email(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nobody@test.local',
        ]);

        $this->assertSuccessResponse($response, 200);
    }

    public function test_forgot_password_sends_reset_link_for_existing_email(): void
    {
        $user = $this->makePatient();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $this->assertSuccessResponse($response, 200);
        $this->assertStringContainsString(
            'password reset link has been sent',
            $response->json('message')
        );
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user  = $this->makePatient();
        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'BrandNew1',
            'password_confirmation' => 'BrandNew1',
        ]);

        $this->assertSuccessResponse($response, 200);
        $this->assertTrue(Hash::check('BrandNew1', $user->fresh()->password));
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = $this->makePatient();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => 'fake-token-99999',
            'email'                 => $user->email,
            'password'              => 'BrandNew1',
            'password_confirmation' => 'BrandNew1',
        ]);

        $this->assertErrorResponse($response, 422);
    }

    public function test_all_tokens_revoked_after_password_reset(): void
    {
        $user     = $this->makePatient();
        $oldToken = $user->createToken('old_device')->plainTextToken;

        $resetToken = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token'                 => $resetToken,
            'email'                 => $user->email,
            'password'              => 'BrandNew1',
            'password_confirmation' => 'BrandNew1',
        ]);

        // old token يجب أن لا يعمل بعد الـ reset
        $response = $this->withToken($oldToken)->getJson('/api/v1/auth/me');
        $this->assertErrorResponse($response, 401);
    }
}
