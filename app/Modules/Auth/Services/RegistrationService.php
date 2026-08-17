<?php

namespace App\Modules\Auth\Services;

use App\Core\Enums\ClinicStatus;
use App\Core\Enums\DoctorVerificationStatus;
use App\Core\Enums\UserRole;
use App\Core\Enums\UserStatus;
use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\Clinic;
use App\Models\ClinicUser;
use App\Models\Doctor;
use App\Models\DoctorClinic;
use App\Models\MedicalHistory;
use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\Receptionist;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

class RegistrationService extends BaseService
{
    public function __construct(
        private readonly UserRoleService $roles,
    ) {}

    public function createBasicAccount(array $data): User
    {
        $role = UserRole::from($data['role']);
        $this->validateRoleCanSelfRegister($role);

        $user = $this->createUserRecord($data,$role);

        if ($data['clinic_code']??null) {
            $clinic = Clinic::where('code', $data['clinic_code'])->first();

            if (! $clinic) {
                throw new NotFoundException('Clinic not found.');
            }

            $clinicId = $clinic->id;
        }

        $this->attachRole($user, $role, clinicId: $clinicId ?? null);
        return $user;
    }

    public function completeProfile(User $user, array $data): void
    {
        $role = UserRole::from((string) $this->roles->getRole($user));

        match ($role) {
            UserRole::Patient      => $this->completePatientProfile($user, $data),
            UserRole::Doctor       => $this->completeDoctorProfile($user, $data),
            UserRole::Receptionist => $this->completeReceptionistProfile($user, $data),
            default                => null,
        };
        $user->update([
            'dob'    => $data['dob'] ?? null,
            'gender' => $data['gender'] ?? null,
            'phone'  => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
        ]);

        event(new Registered($user));
    }

    private function validateRoleCanSelfRegister(UserRole $role): void
    {
        if (! in_array($role->value, UserRole::selfRegisterable(), true)) {
            throw new BusinessException('This account type cannot self-register.');
        }
    }

    public function doctorNeedsApproval(UserRole $role): bool
    {
        return $role === UserRole::Doctor;
    }

    /** @param array<string, mixed> $data */
    private function createUserRecord(array $data,$role): User
    {
        $user = User::query()->create([
            'ID_card_number' => $data['ID_card_number'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'status' =>  UserStatus::Active->value,
        ]);
        return $user;
    }

    /** @param array<string, mixed> $data */
    private function completePatientProfile(User $user, array $data): void
    {
        $patient = Patient::query()->create([
            'user_id' => $user->id,
            'blood_type' => $data['blood_type'] ?? null,
        ]);

        $record = PatientRecord::query()->create([
            'patient_id' => $patient->id,
        ]);

        MedicalHistory::query()->create([
            'patient_record_id' => $record->id,
            'recorded_at'       => null,
        ]);
    }

    private function completeDoctorProfile(User $user, array $data): void
    {
        if ($data['registration_mode'] === 'join_clinic') {
            $clinic = Clinic::where('code', $data['clinic_code'])->first();

            if (! $clinic) {
                throw new NotFoundException('Clinic not found.');
            }

            $clinicId = $clinic->id;
        } else {
            $clinicId = $this->createClinicForDoctor($user, $data);
        }

        $departmentIds = $data['department_ids'];

        if ($data['registration_mode'] === 'join_clinic') {
            $clinicDepartmentRows = array_map(fn ($id) => [
                'department_id' => $id,
                'clinic_id'     => $clinicId,
                'created_at'    => now(),
                'updated_at'    => now(),
            ], $departmentIds);

            DB::table('clinic_departments')->insertOrIgnore($clinicDepartmentRows);
        }

        $doctor = Doctor::query()->create([
            'user_id' => $user->id,
            'practice_start_date' => $data['practice_start_date'] ?? null,
            'verification_status' => DoctorVerificationStatus::Pending->value,
        ]);

        if (isset($data['license_file'])) {
            $doctor->addMedia($data['license_file'])->toMediaCollection('license');
        }

        if (isset($data['id_card'])) {
            $doctor->addMedia($data['id_card'])->toMediaCollection('id_card');
        }
        if (isset($data['photo'])) {
            $doctor->addMedia($data['photo'])->toMediaCollection('photo');
        }

        if (isset($data['certificates'])) {
            foreach ($data['certificates'] as $certificate) {
                $doctor->addMedia($certificate)->toMediaCollection('certificates');
            }
        }

        $doctorDepartmentRows = [];
        foreach ($departmentIds as $index => $departmentId) {
            $doctorDepartmentRows[] = [
                'doctor_id'     => $doctor->id,
                'department_id' => $departmentId,
                'clinic_id'     => $clinicId,
                'is_primary'    => $index === 0,
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }

        DB::table('doctor_departments')->insert($doctorDepartmentRows);

        DoctorClinic::query()->create([
            'doctor_id'        => $doctor->id,
            'clinic_id'        => $clinicId,
            'consultation_fee' => $data['consultation_fee'] ?? null,
        ]);

        $user->clinicUsers()->update(['clinic_id' => $clinicId]);
    }

    private function createClinicForDoctor(User $user, array $data): int
    {
        $clinic = Clinic::query()->create([
            'name' => $data['clinic_name'],
            'address' => $data['clinic_address'],
            'phone' => $data['clinic_phone'] ?? null,
            'owner_id' => $user->id,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'status' => ClinicStatus::Pending->value,
        ]);

        $clinicDepartmentRows = array_map(fn ($id) => [
            'department_id' => $id,
            'clinic_id'     => $clinic->id,
            'created_at'    => now(),
            'updated_at'    => now(),
        ], $data['department_ids']);

        DB::table('clinic_departments')->insertOrIgnore($clinicDepartmentRows);

        if (isset($data['clinic_license_file'])) {
            $clinic->addMedia($data['clinic_license_file'])->toMediaCollection('license');
        }

        return $clinic->id;
    }
    private function completeReceptionistProfile(User $user, array $data): void
    {
        Receptionist::query()->create([
            'user_id' => $user->id,
        ]);
    }

    private function attachRole(User $user, UserRole $role, ?int $clinicId): void
    {
        $roleId = Role::query()
            ->where('name', $role->value)
            ->value('id');

        if (! $roleId) {
            throw new ConflictException(
                "Role [{$role->value}] not found. Ensure RolePermissionSeeder has run."
            );
        }

        ClinicUser::query()->create([
            'clinic_id' => $clinicId,
            'user_id'   => $user->id,
            'role_id'   => $roleId,
        ]);
    }
}
