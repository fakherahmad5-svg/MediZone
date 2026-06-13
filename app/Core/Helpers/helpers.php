<?php

/**
 * app/Core/Helpers/helpers.php
 *
 * Global helper functions للمشروع.
 * تُحمَّل تلقائياً عبر composer.json (autoload.files).
 *
 * القاعدة: نضع هنا فقط functions التي تُستخدَم في أماكن كثيرة
 * وليس من المنطقي وضعها في class محدد.
 */

if (! function_exists('vmc_response')) {
    /**
     * بناء response موحَّد لـ VMC API
     * Shortcut للاستخدام خارج Controllers (مثل: في Middleware)
     *
     * @param bool   $success
     * @param string $message
     * @param mixed  $data
     * @param int    $statusCode
     * @param array  $errors
     */
    function vmc_response(
        bool $success,
        string $message,
        mixed $data = null,
        int $statusCode = 200,
        array $errors = []
    ): \Illuminate\Http\JsonResponse {

        $response = [
            'success' => $success,
            'message' => $message,
        ];

        if (! is_null($data)) {
            $response['data'] = $data;
        }

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }
}

if (! function_exists('paginate_per_page')) {
    /**
     * استخراج قيمة per_page من الـ request مع قيمة افتراضية وحد أقصى
     * يمنع طلب عدد ضخم جداً من السجلات دفعة واحدة
     *
     * مثال: ?per_page=50 → يُرجع 50
     *        ?per_page=500 → يُرجع 50 (الحد الأقصى)
     *        بدون per_page → يُرجع 15 (الافتراضي)
     */
    function paginate_per_page(int $default = 15, int $max = 50): int
    {
        $requested = (int) request()->query('per_page', $default);
        return min($requested, $max);
    }
}

if (! function_exists('is_doctor')) {
    /**
     * التحقق من أن المستخدم الحالي طبيب
     * Shortcut مريح لاستخدامه في Policies والـ Services
     */
    function is_doctor(): bool
    {
        return auth()->check() && auth()->user()->role === 'doctor';
    }
}

if (! function_exists('is_patient')) {
    /**
     * التحقق من أن المستخدم الحالي مريض
     */
    function is_patient(): bool
    {
        return auth()->check() && auth()->user()->role === 'patient';
    }
}

if (! function_exists('is_admin')) {
    function is_admin(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }
}

if (! function_exists('current_clinic_id')) {

    function current_clinic_id(): ?int
    {
        return request()->attributes->get('clinic_id');
    }
}
