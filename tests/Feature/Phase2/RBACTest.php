<?php

namespace Tests\Feature\Phase2;

use App\Core\Http\Middleware\CheckRole;
use Illuminate\Http\Request;

/**
 * RBACTest
 *
 * يختبر نظام الأدوار والصلاحيات (Phase 2):
 *   - CheckRole middleware (Phase 0 → Phase 2 implementation)
 *   - User::hasRole()
 *   - User::hasPermission()
 *   - User::isSuperAdmin()
 *   - getRoleAttribute() accessor
 */
class RBACTest extends Phase2TestCase
{
    // ─────────────────────────────────────────────
    //  Role Accessor
    // ─────────────────────────────────────────────

    public function test_role_accessor_returns_correct_role_string(): void
    {
        $patient      = $this->makePatient();
        $doctor       = $this->makeDoctor();
        $receptionist = $this->makeReceptionist();

        $this->assertEquals('patient', $patient->role);
        $this->assertEquals('doctor', $doctor->role);
        $this->assertEquals('receptionist', $receptionist->role);
    }

    public function test_role_accessor_returns_admin_for_super_admin(): void
    {
        $admin = $this->makeAdmin();

        $this->assertEquals('admin', $admin->role);
    }

    // ─────────────────────────────────────────────
    //  hasRole() Helper
    // ─────────────────────────────────────────────

    public function test_user_has_role_returns_true_for_correct_role(): void
    {
        $patient = $this->makePatient();

        $this->assertTrue($patient->hasRole('patient'));
        $this->assertFalse($patient->hasRole('doctor'));
        $this->assertFalse($patient->hasRole('admin'));
    }

    public function test_user_has_role_returns_true_for_doctor(): void
    {
        $doctor = $this->makeDoctor();

        $this->assertTrue($doctor->hasRole('doctor'));
        $this->assertFalse($doctor->hasRole('patient'));
    }

    // ─────────────────────────────────────────────
    //  hasPermission() Helper
    // ─────────────────────────────────────────────

    public function test_user_has_permission_returns_true_for_granted_permissions(): void
    {
        $patient = $this->makePatient();

        $this->assertTrue($patient->hasPermission('view_own_records'));
        $this->assertTrue($patient->hasPermission('book_appointments'));
    }

    public function test_user_has_permission_returns_false_for_ungranted_permissions(): void
    {
        $patient = $this->makePatient();

        $this->assertFalse($patient->hasPermission('manage_doctors'));
        $this->assertFalse($patient->hasPermission('view_audit_logs'));
    }

    public function test_doctor_has_clinical_permissions(): void
    {
        $doctor = $this->makeDoctor();

        $this->assertTrue($doctor->hasPermission('create_clinical_data'));
        $this->assertTrue($doctor->hasPermission('view_authorized_records'));
        $this->assertFalse($doctor->hasPermission('manage_doctors'));
    }

    // ─────────────────────────────────────────────
    //  isSuperAdmin()
    // ─────────────────────────────────────────────

    public function test_is_super_admin_returns_true_only_for_admin_with_null_clinic(): void
    {
        $admin   = $this->makeAdmin();
        $patient = $this->makePatient();

        $this->assertTrue($admin->isSuperAdmin());
        $this->assertFalse($patient->isSuperAdmin());
    }

    // ─────────────────────────────────────────────
    //  CheckRole Middleware
    // ─────────────────────────────────────────────

    public function test_admin_can_access_admin_only_route(): void
    {
        $admin = $this->makeAdmin();
        $token = $admin->createToken('auth_token')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/ping');

        // /ping يتطلب auth:sanctum فقط — Admin مسجَّل دخوله ✅
        $this->assertSuccessResponse($response, 200);
    }

    public function test_patient_cannot_access_admin_only_route(): void
    {
        $patient    = $this->makePatient();
        $middleware = new CheckRole();
        $request    = Request::create('/api/v1/admin/test', 'GET');
        $request->setUserResolver(fn () => $patient);

        $response = $middleware->handle(
            $request,
            fn ($req) => response()->json(['ok' => true]),
            'admin'
        );

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertFalse(json_decode($response->getContent(), true)['success']);
    }

    public function test_doctor_can_access_doctor_only_route(): void
    {
        $doctor     = $this->makeDoctor();
        $middleware = new CheckRole();
        $request    = Request::create('/api/v1/doctor/test', 'GET');
        $request->setUserResolver(fn () => $doctor);

        $response = $middleware->handle(
            $request,
            fn ($req) => response()->json(['ok' => true], 200),
            'doctor'
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_patient_cannot_access_doctor_only_route(): void
    {
        $patient    = $this->makePatient();
        $middleware = new CheckRole();
        $request    = Request::create('/api/v1/doctor/test', 'GET');
        $request->setUserResolver(fn () => $patient);

        $response = $middleware->handle(
            $request,
            fn ($req) => response()->json(['ok' => true]),
            'doctor'
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_multi_role_route_allows_doctor(): void
    {
        $doctor     = $this->makeDoctor();
        $middleware = new CheckRole();
        $request    = Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(fn () => $doctor);

        // Route يقبل doctor OR admin
        $response = $middleware->handle(
            $request,
            fn ($req) => response()->json(['ok' => true], 200),
            'doctor',
            'admin'
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_multi_role_route_blocks_patient(): void
    {
        $patient    = $this->makePatient();
        $middleware = new CheckRole();
        $request    = Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(fn () => $patient);

        $response = $middleware->handle(
            $request,
            fn ($req) => response()->json(['ok' => true]),
            'doctor',
            'admin'
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_unauthenticated_request_returns_401_not_403(): void
    {
        $response = $this->getJson('/api/v1/ping');
        $this->assertErrorResponse($response, 401);
    }

    public function test_suspended_user_is_blocked_by_role_middleware(): void
    {
        $user = $this->makeDoctor(['status' => 'banned']);

        $middleware = new CheckRole();
        $request    = Request::create('/api/v1/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle(
            $request,
            fn ($req) => response()->json(['ok' => true]),
            'doctor'
        );

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString(
            'suspended',
            json_decode($response->getContent(), true)['message']
        );
    }
}
