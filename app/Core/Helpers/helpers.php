<?php


if (! function_exists('vmc_response')) {

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

    function paginate_per_page(int $default = 15, int $max = 50): int
    {
        $requested = (int) request()->query('per_page', $default);
        return min($requested, $max);
    }
}

if (! function_exists('is_doctor')) {

    function is_doctor(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return app(\App\Modules\Auth\Services\UserRoleService::class)
            ->hasRole(auth()->user(), 'doctor');
    }
}

if (! function_exists('is_patient')) {

    function is_patient(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return app(\App\Modules\Auth\Services\UserRoleService::class)
            ->hasRole(auth()->user(), 'patient');
    }
}

if (! function_exists('is_admin')) {
    function is_admin(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return app(\App\Modules\Auth\Services\UserRoleService::class)
            ->hasRole(auth()->user(), 'admin');
    }
}

if (! function_exists('current_clinic_id')) {

    function current_clinic_id(): ?int
    {
        return request()->attributes->get('clinic_id');
    }
}
