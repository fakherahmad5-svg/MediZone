<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * TestCase Base - تُرث منه كل Feature Tests
 *
 * يحتوي على Helper methods مشتركة لتبسيط كتابة الاختبارات.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * إرفاق Token الـ Sanctum لمستخدم معين في الـ Request
     *
     * مثال:
     *   $this->actingAs($patient)
     *        ->postJson('/api/v1/appointments', [...])
     */
    // actingAs() موجود في Laravel بشكل افتراضي

    /**
     * Helper: التحقق من شكل response النجاح
     *
     * مثال استخدام:
     *   $response = $this->postJson('/api/v1/auth/login', [...]);
     *   $this->assertSuccessResponse($response);
     */
    protected function assertSuccessResponse(
        \Illuminate\Testing\TestResponse $response,
        int $expectedStatus = 200
    ): void {
        $response->assertStatus($expectedStatus)
            ->assertJsonStructure([
                'success',
                'message',
            ])
            ->assertJson(['success' => true]);
    }
    protected function assertErrorResponse(
        \Illuminate\Testing\TestResponse $response,
        int $expectedStatus
    ): void {
        $response->assertStatus($expectedStatus)
            ->assertJsonStructure([
                'success',
                'message',
            ])
            ->assertJson(['success' => false]);
    }


    protected function assertPaginatedResponse(
        \Illuminate\Testing\TestResponse $response
    ): void {
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
                'links' => [
                    'first',
                    'last',
                    'prev',
                    'next',
                ],
            ]);
    }

    /**
     * Helper: التحقق من رسالة Validation Error محددة
     */
    protected function assertValidationError(
        \Illuminate\Testing\TestResponse $response,
        string $field
    ): void {
        $response->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonValidationErrors([$field]);
    }
}
