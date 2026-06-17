<?php

namespace App\Modules\Auth\Services;

use App\Core\Enums\DoctorVerificationStatus;
use App\Core\Enums\UserRole;
use App\Core\Enums\UserStatus;
use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\ConflictException;
use App\Core\Services\BaseService;
use App\Models\ClinicUser;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\Receptionist;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegistrationService extends BaseService
{
    public function createAccount(array $data): User
    {
        $role = UserRole::from($data['role']);
        $user = $this->createUserRecord($data);

        if ($role === UserRole::Patient) {
            $this->createPatient($user, $data);
        }

        if ($role === UserRole::Doctor) {
            $this->createDoctor($user, $data);
        }

        if ($role === UserRole::Receptionist) {
            $this->createReceptionist($user, $data);
        }

        if (! in_array($role, [UserRole::Patient, UserRole::Doctor, UserRole::Receptionist], true)) {
            throw new BusinessException('This account type cannot self-register.');
        }

        return $user;
    }

    public function doctorNeedsApproval(UserRole $role): bool
    {
        return $role === UserRole::Doctor;
    }

    /** @param array<string, mixed> $data */
    private function createUserRecord(array $data): User
    {
        return User::query()->create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'dob' => $data['dob'] ?? null,
            'gender' => $data['gender'] ?? null,
            'status' => UserStatus::Active->value,
        ]);
    }

    /** @param array<string, mixed> $data */
    private function createPatient(User $user, array $data): void
    {
        $patient = Patient::query()->create([
            'user_id' => $user->id,
            'blood_type' => $data['blood_type'] ?? null,
        ]);

        PatientRecord::query()->create(['patient_id' => $patient->id]);
    }

    /** @param array<string, mixed> $data */
    private function createDoctor(User $user, array $data): void
    {
        $doctor = Doctor::query()->create([
            'user_id' => $user->id,
            'license_number' => $data['license_number'],
            'experience_years' => $data['experience_years'] ?? 0,
            'verification_status' => DoctorVerificationStatus::Pending->value,
        ]);

        DB::table('doctor_departments')->insert([
            'doctor_id' => $doctor->id,
            'department_id' => $data['department_id'],
            'clinic_id' => $data['clinic_id'],
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->attachToClinic($user, $data['clinic_id'], UserRole::Doctor);
    }

    /** @param array<string, mixed> $data */
    private function createReceptionist(User $user, array $data): void
    {
        Receptionist::query()->create(['user_id' => $user->id]);
        $this->attachToClinic($user, $data['clinic_id'], UserRole::Receptionist);
    }

    private function attachToClinic(User $user, int $clinicId, UserRole $role): void
    {
        $roleId = Role::query()->where('name', $role->value)->value('id');

        if (! $roleId) {
            throw new ConflictException("Role [{$role->value}] is not configured.");
        }

        ClinicUser::query()->create([
            'clinic_id' => $clinicId,
            'user_id' => $user->id,
            'role_id' => $roleId,
        ]);
    }
}
