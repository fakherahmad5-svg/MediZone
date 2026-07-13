<?php

namespace Tests\Feature\Phase2;

use App\Core\Http\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

/**
 * EmailVerificationTest — UC-C01 (steps 8-10)
 */
class EmailVerificationTest extends Phase2TestCase
{
    // ─────────────────────────────────────────────
    //  Verify Email
    // ─────────────────────────────────────────────

    public function test_user_can_verify_email_with_correct_hash(): void
    {
        $user  = $this->makePatient(['email_verified_at' => null]);
        $token = $user->createToken('auth_token')->plainTextToken;
        $hash  = sha1($user->email);

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/email/verify', ['hash' => $hash]);

        $this->assertSuccessResponse($response, 200);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_email_verification_fails_with_invalid_hash(): void
    {
        $user  = $this->makePatient(['email_verified_at' => null]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/email/verify', [
                'hash' => 'completely-wrong-hash-value',
            ]);

        $this->assertErrorResponse($response, 422);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_already_verified_email_returns_error(): void
    {
        $user  = $this->makePatient(); // verified by default
        $token = $user->createToken('auth_token')->plainTextToken;
        $hash  = sha1($user->email);

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/email/verify', ['hash' => $hash]);

        $this->assertErrorResponse($response, 422);
        $this->assertStringContainsString('already verified', $response->json('message'));
    }

    public function test_email_verification_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/email/verify', [
            'hash' => 'anyhash',
        ]);

        $this->assertErrorResponse($response, 401);
    }

    public function test_verify_requires_hash_field(): void
    {
        $user  = $this->makePatient(['email_verified_at' => null]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/email/verify', []);

        $response->assertStatus(422);
        $this->assertArrayHasKey('hash', $response->json('errors'));
    }

    // ─────────────────────────────────────────────
    //  Resend Verification
    // ─────────────────────────────────────────────

    public function test_unverified_user_can_resend_verification_email(): void
    {
        Notification::fake();

        $user  = $this->makePatient(['email_verified_at' => null]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/email/resend');

        $this->assertSuccessResponse($response, 200);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_fails_if_email_already_verified(): void
    {
        $user  = $this->makePatient(); // verified
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/email/resend');

        $this->assertErrorResponse($response, 422);
        $this->assertStringContainsString('already verified', $response->json('message'));
    }

    public function test_resend_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/email/resend');
        $this->assertErrorResponse($response, 401);
    }

    // ─────────────────────────────────────────────
    //  EnsureEmailIsVerified Middleware
    // ─────────────────────────────────────────────

    public function test_verified_middleware_blocks_unverified_user_with_403(): void
    {
        $user = $this->makePatient(['email_verified_at' => null]);

        $middleware = new EnsureEmailIsVerified();
        $request    = \Illuminate\Http\Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle(
            $request,
            fn ($req) => response()->json(['success' => true], 200)
        );

        $this->assertEquals(403, $response->getStatusCode());

        $body = json_decode($response->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertEquals('EMAIL_NOT_VERIFIED', $body['code']);
    }

    public function test_verified_middleware_allows_verified_user(): void
    {
        $user = $this->makePatient(); // email verified

        $middleware = new EnsureEmailIsVerified();
        $request    = \Illuminate\Http\Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle(
            $request,
            fn ($req) => response()->json(['success' => true], 200)
        );

        $this->assertEquals(200, $response->getStatusCode());
    }
}
