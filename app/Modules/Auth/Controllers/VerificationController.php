<?php

namespace App\Modules\Auth\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Auth\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends BaseController
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    public function verifyCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $this->authService->verifyEmailCode($request->user(), $data['code']);

        return $this->successResponse(
            message: 'Email verified successfully. You now have full access to your account.'
        );
    }

    public function resendCode(Request $request): JsonResponse
    {
        $this->authService->resendVerificationCode($request->user());

        return $this->successResponse(
            message: 'A new verification code has been sent. Please check your inbox.'
        );
    }
}
