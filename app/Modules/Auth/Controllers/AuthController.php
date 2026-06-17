<?php

namespace App\Modules\Auth\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Auth\Data\AuthResult;
use App\Modules\Auth\Requests\ChangePasswordRequest;
use App\Modules\Auth\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Modules\Auth\Requests\ResetPasswordRequest;
use App\Modules\Auth\Resources\AuthUserResource;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Auth\Services\UserRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly UserRoleService $roles,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register([
            ...$request->validated(),
            'device_name' => $request->input('device_name'),
        ]);

        if ($result->requiresVerification) {
            return $this->createdResponse(
                $this->toAuthData($result, withToken: false),
                'Doctor account created successfully and is pending administrator verification.'
            );
        }

        return $this->createdResponse(
            $this->toAuthData($result),
            'Account created successfully.'
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name')
        );

        return $this->successResponse(
            $this->toAuthData($result),
            'Login successful.'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout(
            $request->user(),
            $request->user()->currentAccessToken()
        );

        return $this->successResponse(null, 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->roles->prepareUser($request->user());

        return $this->successResponse([
            'user' => new AuthUserResource($user),
            'dashboard' => $this->roles->getDashboard($user),
        ], 'Authenticated user retrieved successfully.');
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $result = $this->auth->forgotPassword($request->validated('email'));

        $data = isset($result['debug']) ? ['debug' => $result['debug']] : null;

        return $this->successResponse($data, $result['message']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->auth->resetPassword($request->validated());

        return $this->successResponse(null, 'Password has been reset successfully.');
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->auth->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password')
        );

        return $this->successResponse(null, 'Password updated successfully.');
    }

    /** @return array<string, mixed> */
    private function toAuthData(AuthResult $result, bool $withToken = true): array
    {
        $data = [
            'user' => new AuthUserResource($result->user),
            'dashboard' => $this->roles->getDashboard($result->user),
        ];

        if ($result->requiresVerification) {
            $data['requires_verification'] = true;
        }

        if ($withToken && $result->hasToken()) {
            $data['token'] = $result->token;
            $data['token_type'] = $result->tokenType;
        }

        return $data;
    }
}
