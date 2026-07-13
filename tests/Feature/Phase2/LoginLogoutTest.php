<?php

namespace Tests\Feature\Phase2;

use Illuminate\Support\Facades\Event;

/**
 * LoginLogoutTest — UC-C02 / UC-C03
 */
class LoginLogoutTest extends Phase2TestCase
{
    // ─────────────────────────────────────────────
    //  Login — UC-C02
    // ─────────────────────────────────────────────

    public function test_patient_can_login_with_valid_credentials(): void
    {
        $user = $this->makePatient(['password' => 'Password1']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password1',
        ]);

        $this->assertSuccessResponse($response, 200);

        $response->assertJsonStructure([
            'data' => [
                'user'  => ['id', 'email', 'role', 'email_verified'],
                'token',
                'dashboard',
            ],
        ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertEquals('patient', $response->json('data.user.role'));
    }

    public function test_doctor_can_login_and_role_is_correct(): void
    {
        $user = $this->makeDoctor(['password' => 'Password1'], verified: true);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password1',
        ]);

        $this->assertSuccessResponse($response, 200);
        $this->assertEquals('doctor', $response->json('data.user.role'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = $this->makePatient(['password' => 'Password1']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'WrongPassword1',
        ]);

        // BusinessException → 422 (Phase 0 Exception Handler)
        $this->assertErrorResponse($response, 422);
        $this->assertEquals('Invalid email or password.', $response->json('message'));
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'ghost@test.local',
            'password' => 'Password1',
        ]);

        $this->assertErrorResponse($response, 422);
        // رسالة عامة — لا تكشف وجود البريد (User Enumeration Prevention)
        $this->assertEquals('Invalid email or password.', $response->json('message'));
    }

    public function test_login_fails_for_unverified_email(): void
    {
        $user = $this->makePatient([
            'email_verified_at' => null,
            'password'          => 'Password1',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password1',
        ]);

        $this->assertErrorResponse($response, 422);
        $this->assertStringContainsString('verify your email', $response->json('message'));
    }

    public function test_login_fails_for_banned_account(): void
    {
        $user = $this->makePatient([
            'status'   => 'banned',
            'password' => 'Password1',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password1',
        ]);

        $this->assertErrorResponse($response, 422);
        $this->assertStringContainsString('suspended', $response->json('message'));
    }

    public function test_login_fails_for_pending_doctor(): void
    {
        $user = $this->makeDoctor(['password' => 'Password1'], verified: false);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'Password1',
        ]);

        $this->assertErrorResponse($response, 422);
        $this->assertStringContainsString('pending', $response->json('message'));
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422);
        $errors = $response->json('errors');
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    // ─────────────────────────────────────────────
    //  Logout — UC-C03
    // ─────────────────────────────────────────────

    public function test_authenticated_user_can_logout(): void
    {
        $user  = $this->makePatient();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/v1/auth/logout');

        $this->assertSuccessResponse($response, 200);
        $response->assertJsonPath('message', 'Logged out successfully.');
    }

    public function test_token_is_invalidated_after_logout(): void
    {
        $user  = $this->makePatient();
        $token = $user->createToken('auth_token')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout');

        // نفس التوكن بعد الـ logout → 401
        $response = $this->withToken($token)->getJson('/api/v1/auth/me');
        $this->assertErrorResponse($response, 401);
    }

    public function test_logout_without_token_returns_401(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');
        $this->assertErrorResponse($response, 401);
    }

    // ─────────────────────────────────────────────
    //  Me Endpoint
    // ─────────────────────────────────────────────

    public function test_me_returns_authenticated_user_data(): void
    {
        $user  = $this->makePatient();
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $this->assertSuccessResponse($response, 200);
        $this->assertEquals($user->id, $response->json('data.id'));
        $this->assertEquals('patient', $response->json('data.role'));
    }

    public function test_me_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/auth/me');
        $this->assertErrorResponse($response, 401);
    }
}
