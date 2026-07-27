<?php

namespace App\Modules\Doctors\Services;

use App\Core\Enums\ClinicStatus;
use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\Clinic;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\DoctorDepartment;
use App\Models\DoctorProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;


class DoctorProfileService extends BaseService
{
    public function forUser(User $user): Doctor
    {
        $doctor = $user->doctor;

        if (! $doctor) {
            throw new NotFoundException('Doctor profile not found for this account.');
        }

        return $doctor->load(['user','profile', 'departments', 'clinics']);
    }


    public function updateProfile(Doctor $doctor, array $data): DoctorProfile
    {
        return $this->transaction(function () use ($doctor, $data) {
            $userFields = array_filter([
                'first_name' => $data['first_name'] ?? null,
                'last_name'  => $data['last_name'] ?? null,
                'phone'      => $data['phone'] ?? null,
                'address'    => $data['address'] ?? null,
            ], fn ($value) => $value !== null);

            if (! empty($userFields)) {
                $doctor->user()->update($userFields);
            }

            return DoctorProfile::updateOrCreate(
                ['doctor_id' => $doctor->id],
                array_filter([
                    'biography'        => $data['biography'] ?? null,
                    'qualifications'   => $data['qualifications'] ?? null,
                    'consultation_fee' => $data['consultation_fee'] ?? null,
                    'languages'        => $data['languages'] ?? null,
                ], fn ($value) => $value !== null)
            );
        });
    }



    public function updatePhoto(Doctor $doctor, UploadedFile $photo): Doctor
    {
        $doctor->addMedia($photo)->toMediaCollection('photo');

        return $doctor->fresh();
    }


    public function addCertificate(Doctor $doctor, UploadedFile $certificate): Doctor
    {
        $doctor->addMedia($certificate)->toMediaCollection('certificates');

        return $doctor->fresh();
    }


    public function joinClinic(Doctor $doctor, int $clinicId, array $departmentIds): Doctor
    {
        return $this->transaction(function () use ($doctor, $clinicId, $departmentIds) {
            $clinic = Clinic::find($clinicId);

            if (! $clinic) {
                throw new NotFoundException('Clinic not found.');
            }

            if ($clinic->status !== ClinicStatus::Active) {
                throw new BusinessException(
                    'You can only join clinics that are currently active.'
                );
            }

            $ownedDepartmentIds = $doctor->departments()->pluck('departments.id')->unique()->all();
            $invalidIds = array_diff($departmentIds, $ownedDepartmentIds);

            if (! empty($invalidIds)) {
                throw new BusinessException('You can only join using your own registered specialties.');
            }

            $clinicDepartmentRows = array_map(fn ($id) => [
                'clinic_id'     => $clinicId,
                'department_id' => $id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ], $departmentIds);

            DB::table('clinic_departments')->insertOrIgnore($clinicDepartmentRows);

            $doctorDepartmentRows = [];
            foreach ($departmentIds as $index => $departmentId) {
                $doctorDepartmentRows[] = [
                    'doctor_id'     => $doctor->id,
                    'clinic_id'     => $clinicId,
                    'department_id' => $departmentId,
                    'is_primary'    => $index === 0,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }

            DB::table('doctor_departments')->insertOrIgnore($doctorDepartmentRows);

            return $doctor->fresh(['user', 'departments', 'clinics']);
        });
    }


    public function createClinic(Doctor $doctor, array $data): Doctor
    {
        return $this->transaction(function () use ($doctor, $data) {
            $clinic = Clinic::create([
                'name'     => $data['clinic_name'],
                'address'  => $data['clinic_address'],
                'phone'    => $data['clinic_phone'] ?? null,
                'owner_id' => $doctor->user_id,
                'status'   => ClinicStatus::Pending->value,
            ]);

            $clinic->addMedia($data['clinic_license_file'])
                ->toMediaCollection('license');

            DB::table('clinic_departments')->insertOrIgnore([
                'clinic_id'     => $clinic->id,
                'department_id' => $data['department_id'],
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            DoctorDepartment::create([
                'doctor_id'     => $doctor->id,
                'clinic_id'     => $clinic->id,
                'department_id' => $data['department_id'],
                'is_primary'    => $data['is_primary'] ?? false,
            ]);

            return $doctor->fresh(['departments', 'clinics']);
        });
    }


    public function leaveDepartment(Doctor $doctor, int $clinicId, int $departmentId): void
    {
        $this->transaction(function () use ($doctor, $clinicId, $departmentId) {
            $totalLinks = \App\Models\DoctorDepartment::where('doctor_id', $doctor->id)->count();

            if ($totalLinks <= 1) {
                throw new BusinessException(
                    'You must remain linked to at least one clinic/department.'
                );
            }

            DoctorDepartment::where('doctor_id', $doctor->id)
                ->where('clinic_id', $clinicId)
                ->where('department_id', $departmentId)
                ->delete();
        });
    }

}
