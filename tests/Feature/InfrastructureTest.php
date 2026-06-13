<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * InfrastructureTest
 *
 * يتحقق أن البنية التحتية (Phase 0) تعمل بشكل صحيح.
 *
 * التغييرات عن النسخة الأولى:
 *   - test_protected_routes: يستخدم الآن /api/v1/ping بدل /api/v1/records
 *     لأن /records غير موجود في الـ routes بعد (سيُضاف في المرحلة 4)
 *     و /ping موجود داخل auth:sanctum group ويُرجع 401 بدون token بالتأكيد
 */
class InfrastructureTest extends TestCase
{
    /**
     * ✅ اختبار 1: Health Check الأساسي يعمل
     */
    public function test_api_health_check_returns_200(): void
    {
        $response = $this->getJson('/up');

        $response->assertStatus(200);
    }

    /**
     * ✅ اختبار 2: Route غير موجود يُرجع JSON وليس HTML
     */
    public function test_unknown_endpoint_returns_json_404(): void
    {
        $response = $this->getJson('/api/v1/this-does-not-exist');

        $response->assertStatus(404)
            ->assertJson(['success' => false])
            ->assertJsonStructure(['success', 'message']);
    }

    /**
     * ✅ اختبار 3: Auth module جاهز (health endpoint)
     *
     * هذا الـ route موجود في app/Modules/Auth/routes.php
     * إذا فشل هذا الاختبار، معناه أن require base_path(...) لا يعمل
     * أو الملف غير موجود في المسار الصحيح
     */
    public function test_auth_module_health_endpoint_works(): void
    {
        $response = $this->getJson('/api/v1/auth/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'version' => 'v1',
            ]);
    }

    /**
     * ✅ اختبار 4: ForceJsonResponse Middleware يعمل
     * حتى بدون إرسال Accept: application/json header
     */
    public function test_api_forces_json_response_without_accept_header(): void
    {
        // نستخدم get() وليس getJson() — بدون Accept header
        $response = $this->get('/api/v1/this-does-not-exist');

        $response->assertHeader('Content-Type', 'application/json');
    }

    /**
     * ✅ اختبار 5: Protected route بدون token يُرجع 401 وليس redirect
     *
     * نستخدم /api/v1/ping لأنه:
     *   - موجود في routes/api.php داخل auth:sanctum group
     *   - بدون token → يُرجع 401 مباشرة (لا redirect بفضل ForceJsonResponse)
     */
    public function test_protected_routes_return_401_not_redirect(): void
    {
        $response = $this->getJson('/api/v1/ping');

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }
}
