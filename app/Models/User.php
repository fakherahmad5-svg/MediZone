<?php

namespace App\Models;

use App\Core\Enums\Gender;
use App\Core\Enums\UserStatus;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements \Illuminate\Contracts\Auth\MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes,MustVerifyEmail;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'dob'               => 'date',
            'password'          => 'hashed',
            'status'            => UserStatus::class,
            'gender'            => Gender::class,
        ];
    }

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function getRoleAttribute(): ?string
    {
        if ($this->relationLoaded('clinicUsers')) {
            $superAdminEntry = $this->clinicUsers
                ->whereNull('clinic_id')
                ->first();

            $entry = $superAdminEntry ?? $this->clinicUsers->first();

            if ($entry && $entry->relationLoaded('role')) {
                return $entry->role?->name;
            }
        }

        return $this->clinicUsers()
            ->with('role')
            ->orderByRaw('clinic_id IS NULL DESC')
            ->first()
            ?->role
            ?->name;
    }

    public function clinicUsers(): HasMany
    {
        return $this->hasMany(ClinicUser::class);
    }

    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class);
    }

    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    public function receptionist(): HasOne
    {
        return $this->hasOne(Receptionist::class);
    }

    public function ownedClinics(): HasMany
    {
        return $this->hasMany(Clinic::class, 'owner_id');
    }


    public function isBanned(): bool
    {
        return $this->status === UserStatus::Banned
            || $this->getRawOriginal('status') === UserStatus::Banned->value;
    }

    public function isInactive(): bool
    {
        return $this->status === UserStatus::Inactive
            || $this->getRawOriginal('status') === UserStatus::Inactive->value;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active
            || $this->getRawOriginal('status') === UserStatus::Active->value;
    }


    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }
    public function hasRole(string $role, ?int $clinicId = null): bool
    {
        return $this->clinicUsers()
            ->whereHas('role', fn ($q) => $q->where('name', $role))
            ->when(
                $clinicId !== null,
                fn ($q) => $q->where('clinic_id', $clinicId)
            )
            ->exists();
    }

    public function hasPermission(string $permission, ?int $clinicId = null): bool
    {
        return $this->clinicUsers()
            ->when(
                $clinicId !== null,
                fn ($q) => $q->where('clinic_id', $clinicId)
            )
            ->whereHas(
                'role.permissions',
                fn ($q) => $q->where('name', $permission)
            )
            ->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->clinicUsers()
            ->whereNull('clinic_id')
            ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
            ->exists();
    }
    public function sendEmailVerificationNotification(): void
    {
        // Intentionally left blank. Codes are generated and sent
        // explicitly by AuthService::register() / resendVerificationCode().
    }


}
