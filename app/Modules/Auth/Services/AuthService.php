<?php

namespace App\Modules\Auth\Services;

use App\Core\Enums\DoctorVerificationStatus;
use App\Core\Enums\ReceptionistsStatus;
use App\Core\Enums\UserRole;
use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\BusinessException;
use App\Core\Services\BaseService;
use App\Models\AuditLog;
use App\Models\User;
use App\Modules\Auth\Data\AuthResult;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;


class AuthService extends BaseService
{
    public function __construct(
        private readonly RegistrationService $registration,
        private readonly UserRoleService $roles,
        private readonly EmailVerificationService $emailVerification,
        private readonly PasswordResetService $passwordReset,
    ) {}


    // Register / Login / Logout


    public function register(array $data): AuthResult
    {
        return $this->transaction(function () use ($data) {
            $role = UserRole::from($data['role']);
            $user = $this->registration->createBasicAccount($data);

            $this->writeAudit($user, 'register', [
                'role' => $role->value,
                'email' => $user->email,
            ]);

            $this->emailVerification->generateAndSendCode($user);

            $user = $this->roles->prepareUser($user->fresh());
            $device = $data['device_name'] ?? "{$role->label()} Registration";

            return $this->loginResult($user, $device);
        });
    }

    public function login(array $data): AuthResult
    {
        $user = $this->validateLogin($data['email'], $data['password']);
        $user = $this->roles->prepareUser($user);

        $this->writeAudit($user, 'login', [
            'role' => $this->roles->getRole($user),
            'email' => $user->email,
        ]);

        return $this->loginResult($user, $deviceName ?? 'VMC Login');
    }

    public function logout(User $user, ?PersonalAccessToken $token = null): void
    {
        $this->revokeToken($token);

        $this->writeAudit($user, 'logout', [
            'role' => $this->roles->getRole($user),
        ]);
    }


    // Password


    public function forgotPassword(string $email): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $this->passwordReset->generateAndSendCode($user);
        }

        return [
            'message' => 'If this email exists, a reset code has been sent.',
        ];
    }

    public function resetPassword(string $email, string $code, string $newPassword): void
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            throw new BusinessException('Invalid or expired reset code.');
        }

        $this->passwordReset->verifyCodeAndReset($user, $code, $newPassword);

        $this->writeAudit($user, 'password_reset', ['email' => $user->email]);
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw new AuthorizationException('Current password is incorrect.');
        }
        $user->forceFill([
            'password' => $newPassword,
        ])->save();
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();

        $this->writeAudit($user, 'password_change', ['email' => $user->email]);
    }


    // Helpers

    private function validateLogin(string $email, string $password): User
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new BusinessException('Invalid email or password.');
        }

        $user->load('doctor');
        $this->ensureAccountIsActive($user);

        return $user;
    }

    private function ensureAccountIsActive(User $user): void
    {

        if ($user->isBanned()) {
            throw new BusinessException('Your account has been suspended. Please contact support.');
        }

        if ($user->isInactive()) {
            throw new BusinessException('Your account is inactive. Please contact support.');
        }

        if (! $user->hasVerifiedEmail()) {
            throw new BusinessException(
                'Please verify your email address before logging in. '
            );
        }

        if ($user->patient) {
            return;
        }
        if ($user->doctor) {
            $messages = [
                DoctorVerificationStatus::Pending->value => 'Your doctor account is pending administrator verification.',
                DoctorVerificationStatus::Rejected->value => 'Your doctor verification was rejected. Please contact support.',
                DoctorVerificationStatus::Suspended->value => 'Your doctor account has been suspended.',
            ];

            $status = $user->doctor->verification_status instanceof DoctorVerificationStatus
                ? $user->doctor->verification_status->value
                : $user->doctor->verification_status;

            if (isset($messages[$status])) {
                throw new BusinessException($messages[$status]);
            }
        }
    }

    private function loginResult(User $user, string $deviceName): AuthResult
    {
        if($user->tokens()){
        $user->tokens()->delete();
        }
        $token = $user->createToken($deviceName, $this->roles->getPermissions($user));

        return new AuthResult(user: $user, token: $token->plainTextToken);
    }

    private function revokeToken(?PersonalAccessToken $token): void
    {
        if ($token instanceof PersonalAccessToken) {
            $token->delete();

            return;
        }

        $bearer = request()->bearerToken();

        if (! $bearer) {
            return;
        }

        $found = PersonalAccessToken::findToken($bearer);

        if ($found) {
            $found->delete();

            return;
        }

        if (str_contains($bearer, '|')) {
            [$id] = explode('|', $bearer, 2);
            PersonalAccessToken::query()->whereKey($id)->delete();
        }
    }

    /** @param array<string, mixed> $details */
    private function writeAudit(User $user, string $action, array $details = []): void
    {
        AuditLog::query()->create([
            'user_id' => $user->id,
            'clinic_id' => $user->clinicUsers()->value('clinic_id'),
            'action' => "auth.{$action}",
            'entity_type' => User::class,
            'entity_id' => $user->id,
            'old_values' => null,
            'new_values' => $details,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }


    public function verifyEmailCode(User $user, string $code): void
    {
        $this->emailVerification->verifyCode($user, $code);

        $this->writeAudit($user, 'email_verified', [
            'email' => $user->email,
        ]);
    }

    public function completeProfile(User $user, array $data): AuthResult
    {
        return $this->transaction(callback: function () use ($user, $data) {
            if (! $user->hasVerifiedEmail()) {
                throw new BusinessException(
                    'Please verify your email address before completing your profile.'
                );
            }

            $this->registration->completeProfile($user, $data);

            $user = $this->roles->prepareUser($user->fresh());

            $role = $this->roles->getRole($user);

            if ($role === UserRole::Doctor->value ) {
                $user->tokens()->delete();
                return new AuthResult(user: $user, pendingApproval: true);
            }

            $this->writeAudit($user, 'profile_completed', [
                'email' => $user->email,
            ]);

            return new AuthResult(user: $user);
        });
    }

    public function resendVerificationCode(User $user): void
    {
        $this->emailVerification->generateAndSendCode($user);

        $this->writeAudit($user, 'email_verification_resent', [
            'email' => $user->email,
        ]);
    }
}
