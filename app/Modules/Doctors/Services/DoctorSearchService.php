<?php

namespace App\Modules\Doctors\Services;

use App\Core\Enums\ClinicStatus;
use App\Core\Enums\DoctorVerificationStatus;
use App\Core\Exceptions\NotFoundException;
use App\Models\Doctor;
use Illuminate\Pagination\LengthAwarePaginator;


class DoctorSearchService
{

    public function search(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Doctor::query()
            ->with(['user:id,first_name,last_name', 'profile', 'departments', 'clinics'])
            ->where('verification_status', DoctorVerificationStatus::Verified->value)
            ->whereHas('clinics', fn ($q) => $q->where('status', ClinicStatus::Active->value))

            ->when(
                ! empty($filters['department_id']),
                fn ($q) => $q->whereHas(
                    'departments',
                    fn ($dq) => $dq->where('departments.id', $filters['department_id'])
                )
            )

            ->when(
                ! empty($filters['clinic_id']),
                fn ($q) => $q->whereHas(
                    'clinics',
                    fn ($cq) => $cq->where('clinics.id', $filters['clinic_id'])
                )
            )

            ->when(
                ! empty($filters['name']),
                fn ($q) => $q->whereHas(
                    'user',
                    fn ($uq) => $uq->where(
                        fn ($sub) => $sub
                            ->where('first_name', 'like', "%{$filters['name']}%")
                            ->orWhere('last_name', 'like', "%{$filters['name']}%")
                    )
                )
            )

            ->orderByDesc('avg_rating')
            ->paginate($perPage);
    }


    public function publicProfile(int $doctorId): Doctor
    {
        $doctor = Doctor::query()
            ->with(['user:id,first_name,last_name', 'profile', 'departments', 'clinics'])
            ->where('verification_status', DoctorVerificationStatus::Verified->value)
            ->whereHas('clinics', fn ($q) => $q->where('status', ClinicStatus::Active->value))
            ->find($doctorId);

        if (! $doctor) {
            throw new NotFoundException('Doctor not found or not currently available.');
        }

        return $doctor;
    }
}
