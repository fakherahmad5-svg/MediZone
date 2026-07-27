<?php

namespace App\Modules\Doctors\Services;

use App\Core\Enums\DoctorVerificationStatus;
use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;


class DoctorVerificationService extends BaseService
{
    public function findOrFail(int $id): Doctor
    {
        $doctor = Doctor::with(['user:id,first_name,last_name,email', 'profile'])->find($id);

        if (! $doctor) {
            throw new NotFoundException('Doctor not found.');
        }

        return $doctor;
    }


    public function pendingList(int $perPage = 15): LengthAwarePaginator
    {
        return Doctor::query()
            ->where('verification_status', DoctorVerificationStatus::Pending->value)
            ->with(['user:id,first_name,last_name,email', 'profile', 'departments', 'clinics'])
            ->oldest('id')
            ->paginate($perPage);
    }


    public function paginateForAdmin(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Doctor::query()
            ->with(['user:id,first_name,last_name,email', 'profile'])
            ->when(
                ! empty($filters['status']),
                fn ($q) => $q->where('verification_status', $filters['status'])
            )
            ->when(
                ! empty($filters['department_id']),
                fn ($q) => $q->whereHas(
                    'departments',
                    fn ($dq) => $dq->where('departments.id', $filters['department_id'])
                )
            )
            ->latest('id')
            ->paginate($perPage);
    }


    public function approve(Doctor $doctor, User $admin): Doctor
    {
        return $this->transaction(function () use ($doctor, $admin) {
            $this->ensureIsPending($doctor);

            $doctor->update([
                'verification_status' => DoctorVerificationStatus::Verified->value,
            ]);

            $this->writeAudit($admin, $doctor, 'doctor_verified');

            return $doctor->fresh();
        });
    }


    public function reject(Doctor $doctor, User $admin, string $reason): Doctor
    {
        return $this->transaction(function () use ($doctor, $admin, $reason) {
            $this->ensureIsPending($doctor);

            $doctor->update([
                'verification_status' => DoctorVerificationStatus::Rejected->value,
            ]);

            $this->writeAudit($admin, $doctor, 'doctor_rejected', ['reason' => $reason]);

            return $doctor->fresh();
        });
    }

    public function suspend(Doctor $doctor, User $admin, string $reason): Doctor
    {
        return $this->transaction(function () use ($doctor, $admin, $reason) {
            if (! $doctor->isVerified()) {
                throw new BusinessException('Only verified doctors can be suspended.');
            }

            $doctor->update([
                'verification_status' => DoctorVerificationStatus::Suspended->value,
            ]);

            $this->writeAudit($admin, $doctor, 'doctor_suspended', ['reason' => $reason]);

            return $doctor->fresh();
        });
    }

    public function reactivate(Doctor $doctor, User $admin): Doctor
    {
        return $this->transaction(function () use ($doctor, $admin) {
            if (! $doctor->isSuspended()) {
                throw new BusinessException('Only suspended doctors can be reactivated.');
            }

            $doctor->update([
                'verification_status' => DoctorVerificationStatus::Verified->value,
            ]);

            $this->writeAudit($admin, $doctor, 'doctor_reactivated');

            return $doctor->fresh();
        });
    }

    // ─────────────────────────────────────────────────────────────


    private function ensureIsPending(Doctor $doctor): void
    {
        if (! $doctor->isPending()) {
            throw new BusinessException('This doctor is not awaiting verification.');
        }
    }

    private function writeAudit(User $admin, Doctor $doctor, string $action, array $details = []): void
    {
        AuditLog::query()->create([
            'user_id'     => $admin->id,
            'clinic_id'   => null,
            'action'      => $action,
            'entity_type' => Doctor::class,
            'entity_id'   => $doctor->id,
            'old_values'  => null,
            'new_values'  => $details,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);
    }
}
