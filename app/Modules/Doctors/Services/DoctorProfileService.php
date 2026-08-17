<?php

namespace App\Modules\Doctors\Services;

use App\Core\Enums\ClinicStatus;
use App\Core\Enums\UserRole;
use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\Clinic;
use App\Models\ClinicUser;
use App\Models\Department;
use App\Models\Doctor;
use App\Models\DoctorClinic;
use App\Models\DoctorDepartment;
use App\Models\DoctorProfile;
use App\Models\Role;
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
                    'online_consultation_fee' => $data['online_consultation_fee'] ?? null,
                    'languages'        => $data['languages'] ?? null,
                ], fn ($value) => $value !== null)
            );
        });
    }

    public function updateConsultationFee(Doctor $doctor, int $clinicId, float $consultationFee): DoctorClinic
    {
        $doctorClinic = DoctorClinic::where('doctor_id', $doctor->id)
            ->where('clinic_id', $clinicId)
            ->first();

        if (! $doctorClinic) {
            throw new NotFoundException('You are not a member of this clinic.');
        }

        $doctorClinic->update(['consultation_fee' => $consultationFee]);

        return $doctorClinic;
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


    public function joinClinic(Doctor $doctor, string $clinicCode,float $consultationFee): Doctor
    {
        return $this->transaction(function () use ($doctor, $clinicCode,$consultationFee) {
            $clinic = Clinic::where('code', $clinicCode)->first();

            if (! $clinic) {
                throw new NotFoundException('Clinic not found.');
            }
            $clinicId = $clinic->id;
            if ($clinic->status !== ClinicStatus::Active) {
                throw new BusinessException(
                    'You can only join clinics that are currently active.'
                );
            }
            if (ClinicUser::where('clinic_id', $clinic->id)->where('user_id',$doctor->user->id)->exists()) {
                throw new ConflictException('Clinic already joined.');
            }
            $ownedDepartmentIds = $doctor->departments()->pluck('departments.id')->unique()->all();


            if (! empty($invalidIds)) {
                throw new BusinessException('You can only join using your own registered specialties.');
            }

            $clinicDepartmentRows = array_map(fn ($id) => [
                'clinic_id'     => $clinicId,
                'department_id' => $id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ], $ownedDepartmentIds);

            DB::table('clinic_departments')->insertOrIgnore($clinicDepartmentRows);

            $doctorDepartmentRows = [];
            foreach ($ownedDepartmentIds as $index => $departmentId) {
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
            $this->attachDoctorToClinic($doctor->user, $clinicId,);

            DoctorClinic::query()->create([
                'doctor_id'        => $doctor->id,
                'clinic_id'        => $clinicId,
                'consultation_fee' => $consultationFee,
            ]);

            return $doctor->fresh(['user', 'departments', 'clinics']);
        });
    }


    public function createClinic(Doctor $doctor, array $data, float $consultationFee): Doctor
    {
        return $this->transaction(function () use ($doctor, $data,$consultationFee) {
            $clinic = Clinic::create([
                'name'     => $data['clinic_name'],
                'address'  => $data['clinic_address'],
                'phone'    => $data['clinic_phone'] ?? null,
                'owner_id' => $doctor->user_id,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'status'   => ClinicStatus::Pending->value,
            ]);

            $clinic->addMedia($data['clinic_license_file'])
                ->toMediaCollection('license');
            $ownedDepartmentIds = $doctor->departments()->pluck('departments.id')->unique()->all();
            $clinicDepartmentRows = array_map(fn ($id) => [
                'clinic_id'     => $clinic->id,
                'department_id' => $id,
                'created_at'    => now(),
                'updated_at'    => now(),
            ], $ownedDepartmentIds);
            DB::table('clinic_departments')->insertOrIgnore($clinicDepartmentRows);

            $doctorDepartmentRows = [];
            foreach ($ownedDepartmentIds as $index => $departmentId) {
                $doctorDepartmentRows[] = [
                    'doctor_id'     => $doctor->id,
                    'clinic_id'     => $clinic->id,
                    'department_id' => $departmentId,
                    'is_primary'    => $index === 0,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ];
            }

            DB::table('doctor_departments')->insertOrIgnore($doctorDepartmentRows);
            $this->attachDoctorToClinic($doctor->user, $clinic->id,);

            DoctorClinic::query()->create([
                'doctor_id'        => $doctor->id,
                'clinic_id'        => $clinic->id,
                'consultation_fee' => $consultationFee,
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

    private function attachDoctorToClinic(User $user, int $clinicId): void
    {
        $roleId = Role::query()
            ->where('name', 'doctor')
            ->value('id');
        ClinicUser::query()->create([
            'clinic_id' => $clinicId,
            'user_id'   => $user->id,
            'role_id'   => $roleId,
        ]);
    }

}
