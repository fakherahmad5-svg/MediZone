<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * InfrastructureTest
 *
 * أول اختبار في المشروع — يتحقق أن البنية التحتية تعمل بشكل صحيح.
 *
 * هذا الاختبار لا يختبر business logic بل يختبر:
 *   1. الـ API يستجيب
 *   2. شكل الـ responses صحيح
 *   3. الـ Exception Handler يعمل
 *
 * تشغيل الاختبار:
 *   php artisan test tests/Feature/InfrastructureTest.php
 */
class InfrastructureTest extends TestCase
{
    /**
     * اختبار أن الـ API Health Check يعمل
     */
    public function test_api_health_check_returns_200(): void
    {
        $response = $this->getJson('/up');

        $response->assertStatus(200);
    }

    /**
     * اختبار أن endpoint غير موجود يُرجع 404 بـ JSON format
     * وليس HTML page
     */
    public function test_unknown_endpoint_returns_json_404(): void
    {
        $response = $this->getJson('/api/v1/this-does-not-exist');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonStructure([
                'success',
                'message',
            ]);
    }

    /**
     * اختبار أن Auth module جاهز (placeholder route)
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
     * اختبار أن الـ API يُرجع JSON حتى بدون Accept header
     * (ForceJsonResponse Middleware يعمل)
     */
    public function test_api_forces_json_response_without_accept_header(): void
    {
        $response = $this->get('/api/v1/this-does-not-exist');

        // حتى بدون getJson() (بدون Accept: application/json)
        // يجب أن يُرجع JSON وليس HTML
        $response->assertHeader('Content-Type', 'application/json');
    }

    /**
     * اختبار أن محاولة الوصول لـ protected route بدون token تُرجع 401
     * وليس redirect لـ /login
     */
    public function test_protected_routes_return_401_not_redirect(): void
    {
        // نحاول الوصول لأي route محمي بدون token
        $response = $this->getJson('/api/v1/records');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);
    }
}
