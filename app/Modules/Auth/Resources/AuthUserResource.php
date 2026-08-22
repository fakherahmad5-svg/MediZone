<?php

namespace App\Modules\Auth\Resources;

use App\Core\Enums\UserRole;
use App\Core\Http\Resources\BaseResource;
use App\Models\User;
use App\Modules\Auth\Services\UserRoleService;

/** @mixin User */
class AuthUserResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        $roleService = app(UserRoleService::class);
        $role = $roleService->getRole($this->resource);

        return [

            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim("{$this->first_name} {$this->last_name}"),
            'email' => $this->email,
            'phone' => $this->phone,
            'dob' => $this->dob?->toDateString(),
            'gender' => $this->gender,
            'address' => $this->address,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'role' => $role,
            'role_label' => UserRole::tryFrom((string) $role)?->label(),

            'profile' => $this->profileByRole($role),
        ];
    }

    /** @return array<int, array<string, mixed>>|null */
    private function clinicsList(): ?array
    {
        if (! $this->relationLoaded('clinicUsers')) {
            return null;
        }

        $list = [];

        foreach ($this->clinicUsers as $membership) {
            $list[] = [
                'clinic_id' => $membership->clinic_id,
                'clinic_name' => $membership->clinic?->name,
                'role' => $membership->role?->name,
            ];
        }

        return empty($list) ? null : $list;
    }

    /** @return array<int, array<string, mixed>>|null */
    private function doctorClinicsList(): ?array
    {
        if (! $this->doctor->relationLoaded('clinics')) {
            return null;
        }

        $list = [];

        foreach ($this->doctor->clinics as $clinic) {
            $list[] = [
                'clinic_id' => $clinic->id,
                'clinic_name' => $clinic->name,
                'clinic_code' => $clinic->code,
                'consultation_fee' => $clinic->pivot->consultation_fee,
            ];
        }

        return empty($list) ? null : $list;
    }

    /** @return array<string, mixed>|null */
    private function profileByRole(?string $role): ?array
    {
        if ($role === UserRole::Patient->value) {
            return $this->patientProfile();
        }

        if ($role === UserRole::Doctor->value) {
            return $this->doctorProfile();
        }

        if ($role === UserRole::Receptionist->value) {
            return $this->receptionistProfile();
        }

        if ($role === UserRole::Admin->value) {
            return ['is_super_admin' => true];
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function patientProfile(): ?array
    {
        if (! $this->relationLoaded('patient') || ! $this->patient) {
            return null;
        }

        $recordId = null;

        if ($this->patient->relationLoaded('patientRecord')) {
            $recordId = $this->patient->patientRecord?->id;
        }

        return [
            'patient_id' => $this->patient->id,
            'blood_type' => $this->patient->blood_type,
            'patient_record_id' => $recordId,
            'has_medical_data' => $recordId !== null
                ? $this->patient->patientRecord->hasMedicalData()
                : false,
        ];
    }

    /** @return array<string, mixed>|null */
    private function doctorProfile(): ?array
    {
        if (! $this->relationLoaded('doctor') || ! $this->doctor) {
            return null;
        }

        $departments = [];

        if ($this->doctor->relationLoaded('departments')) {
            foreach ($this->doctor->departments as $department) {
                $departments[] = [
                    'id' => $department->id,
                    'name' => $department->name,
                    'clinic_id' => $department->pivot->clinic_id,
                    'is_primary' => (bool) $department->pivot->is_primary,
                ];
            }
        }

        return [
            'doctor_id' => $this->doctor->id,
            'practice_start_date' => $this->doctor->practice_start_date,
            'experience_years' => $this->doctor->experience_years,
            'verification_status' => $this->doctor->verification_status,
            'departments' => $departments,
            'clinics' => $this->doctorClinicsList(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function receptionistProfile(): ?array
    {
        if (! $this->relationLoaded('receptionist') || ! $this->receptionist) {
            return null;
        }

        return [
            'receptionist_id' => $this->receptionist->id,
            'clinic'=> $this->clinicsList()
        ];
    }
}
