<?php

namespace App\Modules\Auth\Services;

use App\Core\Enums\UserRole;
use App\Models\ClinicUser;
use App\Models\Role;
use App\Models\User;

class UserRoleService
{
    /** @var array<int, string|null> */
    private array $roleCache = [];

    /** @var array<int, list<string>> */
    private array $permissionsCache = [];

    public function prepareUser(User $user): User
    {
        return $user->load([
            'clinicUsers.role',
            'clinicUsers.clinic:id,name',
            'patient.patientRecord',
            'doctor.departments:id,name',
            'receptionist',
        ]);
    }

    public function getRole(User $user): ?string
    {
        if (isset($this->roleCache[$user->id])) {
            return $this->roleCache[$user->id];
        }

        $role = $this->getClinicRole($user);

        if ($role === null && $this->hasPatientProfile($user)) {
            $role = UserRole::Patient->value;
        }

        $this->roleCache[$user->id] = $role;

        return $role;
    }

    public function getDashboard(User $user): ?string
    {
        $role = $this->getRole($user);

        if ($role === null) {
            return null;
        }

        return UserRole::tryFrom($role)?->dashboard();
    }

    /** @return list<string> */
    public function getPermissions(User $user): array
    {
        if (isset($this->permissionsCache[$user->id])) {
            return $this->permissionsCache[$user->id];
        }

        $roleName = $this->getRole($user);

        if ($roleName === null) {
            $this->permissionsCache[$user->id] = [];

            return [];
        }

        $role = Role::query()
            ->where('name', $roleName)
            ->with('permissions:id,name')
            ->first();

        $permissions = [];

        if ($role) {
            foreach ($role->permissions as $permission) {
                $permissions[] = $permission->name;
            }
        }

        $this->permissionsCache[$user->id] = $permissions;

        return $permissions;
    }

    public function hasRole(User $user, string ...$roles): bool
    {
        return in_array($this->getRole($user), $roles, true);
    }

    private function getClinicRole(User $user): ?string
    {
        if ($user->relationLoaded('clinicUsers')) {
            foreach ($user->clinicUsers as $membership) {
                if ($membership->relationLoaded('role') && $membership->role) {
                    return $membership->role->name;
                }
            }
        }

        $membership = $user->clinicUsers()->with('role')->first();

        if (! $membership || ! $membership->role) {
            return null;
        }

        return $membership->role->name;
    }

    private function hasPatientProfile(User $user): bool
    {
        if ($user->relationLoaded('patient')) {
            return (bool) $user->patient;
        }

        return $user->patient()->exists();
    }
}
