<?php

namespace App\Modules\Auth\Services;

use App\Core\Enums\DoctorVerificationStatus;
use App\Core\Enums\UserRole;
use App\Core\Enums\UserStatus;
use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\BusinessException;
use App\Core\Services\BaseService;
use App\Models\AuditLog;
use App\Models\User;
use App\Modules\Auth\Data\AuthResult;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService extends BaseService
{
    public function __construct(
        private readonly RegistrationService $registration,
        private readonly UserRoleService $roles,
    ) {}

    // -------------------------------------------------------------------------
    // Register / Login / Logout
    // -------------------------------------------------------------------------

    public function register(array $data): AuthResult
    {
        return $this->transaction(function () use ($data) {
            $role = UserRole::from($data['role']);
            $user = $this->registration->createAccount($data);

            $this->writeAudit($user, 'register', [
                'role' => $role->value,
                'email' => $user->email,
            ]);

            $user = $this->roles->prepareUser($user->fresh());

            if ($this->registration->doctorNeedsApproval($role)) {
                return new AuthResult(user: $user, requiresVerification: true);
            }

            $device = $data['device_name'] ?? "{$role->label()} Registration";

            return $this->loginResult($user, $device);
        });
    }

    public function login(string $email, string $password, ?string $deviceName = null): AuthResult
    {
        $user = $this->validateLogin($email, $password);
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

    // -------------------------------------------------------------------------
    // Password
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    public function forgotPassword(string $email): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            Password::sendResetLink(['email' => $email]);
        }

        $response = [
            'message' => 'Password reset instructions have been sent if the account exists.',
        ];

        if ($user && config('app.debug')) {
            $response['debug'] = [
                'reset_token' => Password::broker()->createToken($user),
                'reset_endpoint' => url('/api/v1/auth/reset-password'),
            ];
        }

        return $response;
    }

    /** @param array<string, mixed> $data */
    public function resetPassword(array $data): void
    {
        $status = Password::reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'] ?? $data['password'],
                'token' => $data['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
                event(new PasswordReset($user));

                $this->writeAudit($user, 'password_reset', ['email' => $user->email]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new BusinessException(__($status));
        }
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw new AuthorizationException('Current password is incorrect.');
        }

        $user->update(['password' => $newPassword]);

        $this->writeAudit($user, 'password_change', ['email' => $user->email]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function validateLogin(string $email, string $password): User
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw new AuthorizationException('Invalid email or password.');
        }

        $user->load('doctor');
        $this->ensureAccountIsActive($user);

        return $user;
    }

    private function ensureAccountIsActive(User $user): void
    {
        if ($user->isBanned()) {
            throw new AuthorizationException('Your account has been suspended. Please contact support.');
        }

        if ($user->status === UserStatus::Inactive->value) {
            throw new AuthorizationException('Your account is inactive. Please contact support.');
        }

        if (! $user->isActive()) {
            throw new AuthorizationException('Your account is not active.');
        }

        if (! $user->doctor) {
            return;
        }

        $messages = [
            DoctorVerificationStatus::Pending->value => 'Your doctor account is pending administrator verification.',
            DoctorVerificationStatus::Rejected->value => 'Your doctor verification was rejected. Please contact support.',
            DoctorVerificationStatus::Suspended->value => 'Your doctor account has been suspended.',
        ];

        $status = $user->doctor->verification_status;

        if (isset($messages[$status])) {
            throw new AuthorizationException($messages[$status]);
        }
    }

    private function loginResult(User $user, string $deviceName): AuthResult
    {
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
}
