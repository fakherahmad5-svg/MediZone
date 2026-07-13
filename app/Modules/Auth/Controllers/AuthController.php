<?php

namespace App\Modules\Auth\Controllers;

use App\Core\Http\Controllers\BaseController;
use App\Modules\Auth\Data\AuthResult;
use App\Modules\Auth\Requests\ChangePasswordRequest;
use App\Modules\Auth\Requests\CompleteProfileRequest;
use App\Modules\Auth\Requests\ForgotPasswordRequest;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Requests\RegisterRequest;
use App\Modules\Auth\Requests\ResetPasswordRequest;
use App\Modules\Auth\Resources\AuthUserResource;
use App\Modules\Auth\Resources\BasicAuthUserResource;
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
        $result = $this->auth->register($request->validated());
        if ($result->requiresVerification) {
            return $this->createdResponse(
                $this->toAuthData($result, withToken: false),
                'Registration successful. Your account is pending administrator verification.'
            );
        }
        return $this->createdResponse(
            $this->toAuthData($result),
            'Registration successful. Please verify your email address.'
        );
    }

    public function completeProfile(CompleteProfileRequest $request): JsonResponse
    {
        $result = $this->auth->completeProfile(
            $request->user(),
            $request->validated()
        );

        if ($result->pendingApproval) {
            return $this->successResponse(
                $this->toAuthData($result, withToken: false),
                'Profile completed. Your account is pending administrator verification. ' .
                'You will be able to log in once approved.'
            );
        }
        return $this->successResponse(
            $this->toAuthData($result),
            'Profile completed successfully.'
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login($request->validated());

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
        $this->auth->resetPassword(
            $request->validated('email'),
            $request->validated('code'),
            $request->validated('password')
        );

        return $this->successResponse(null, 'Password has been reset successfully.'); }

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
        if($result->user->hasVerifiedEmail()){
        $data = [
            'user' => new AuthUserResource($result->user),
        ];
        }else{
            $data = [
                'user' => new BasicAuthUserResource($result->user),
            ];
        }


        if ($withToken && $result->hasToken()) {
            $data['token'] = $result->token;
            $data['token_type'] = $result->tokenType;
        }

        return $data;
    }
}
