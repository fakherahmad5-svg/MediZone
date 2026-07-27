<?php

namespace App\Modules\Receptionists\Services;

use App\Core\Enums\ReceptionistsStatus;
use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\AuditLog;
use App\Models\Receptionist;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;


class ReceptionistVerificationService extends BaseService
{
    public function findOrFail(int $id): Receptionist
    {
        $receptionist = Receptionist::with(['user:id,first_name,last_name,email'])->find($id);

        if (! $receptionist) {
            throw new NotFoundException('Receptionist not found.');
        }

        return $receptionist;
    }

    public function pendingList(int $perPage = 15): LengthAwarePaginator
    {
        return Receptionist::query()
            ->where('status', ReceptionistsStatus::Pending->value)
            ->with('user:id,first_name,last_name,email')
            ->oldest('id')
            ->paginate($perPage);
    }

    /**
     * @param array{status?: string, clinic_id?: int} $filters
     */
    public function paginateForAdmin(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Receptionist::query()
            ->with(['user:id,first_name,last_name,email', 'user.clinicUsers.clinic:id,name'])
            ->when(
                ! empty($filters['status']),
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when(
                ! empty($filters['clinic_id']),
                fn ($q) => $q->whereHas(
                    'user.clinicUsers',
                    fn ($cq) => $cq->where('clinic_id', $filters['clinic_id'])
                )
            )
            ->latest('id')
            ->paginate($perPage);
    }

    public function approve(Receptionist $receptionist, User $admin): Receptionist
    {
        return $this->transaction(function () use ($receptionist, $admin) {
            $this->ensureIsPending($receptionist);

            $receptionist->update([
                'status'       => ReceptionistsStatus::Approved->value,
                'review_notes' => null,
            ]);

            $this->writeAudit($admin, $receptionist, 'receptionist_approved');

            return $receptionist->fresh();
        });
    }

    public function reject(Receptionist $receptionist, User $admin, string $reason): Receptionist
    {
        return $this->transaction(function () use ($receptionist, $admin, $reason) {
            $this->ensureIsPending($receptionist);

            $receptionist->update([
                'status'       => ReceptionistsStatus::Rejected->value,
                'review_notes' => $reason,
            ]);

            $this->writeAudit($admin, $receptionist, 'receptionist_rejected', ['reason' => $reason]);

            return $receptionist->fresh();
        });
    }

    public function suspend(Receptionist $receptionist, User $admin, string $reason): Receptionist
    {
        return $this->transaction(function () use ($receptionist, $admin, $reason) {
            if (! $receptionist->isApproved()) {
                throw new BusinessException('Only approved receptionists can be suspended.');
            }

            $receptionist->update([
                'status'       => ReceptionistsStatus::Suspended->value,
                'review_notes' => $reason,
            ]);

            $this->writeAudit($admin, $receptionist, 'receptionist_suspended', ['reason' => $reason]);

            return $receptionist->fresh();
        });
    }

    public function reactivate(Receptionist $receptionist, User $admin): Receptionist
    {
        return $this->transaction(function () use ($receptionist, $admin) {
            if (! $receptionist->isSuspended()) {
                throw new BusinessException('Only suspended receptionists can be reactivated.');
            }

            $receptionist->update([
                'status'       => ReceptionistsStatus::Approved->value,
                'review_notes' => null,
            ]);

            $this->writeAudit($admin, $receptionist, 'receptionist_reactivated');

            return $receptionist->fresh();
        });
    }

    // ─────────────────────────────────────────────────────────────

    private function ensureIsPending(Receptionist $receptionist): void
    {
        if (! $receptionist->isPending()) {
            throw new BusinessException('This receptionist is not awaiting review.');
        }
    }

    private function writeAudit(User $admin, Receptionist $receptionist, string $action, array $details = []): void
    {
        AuditLog::query()->create([
            'user_id'     => $admin->id,
            'clinic_id'   => null,
            'action'      => $action,
            'entity_type' => Receptionist::class,
            'entity_id'   => $receptionist->id,
            'old_values'  => null,
            'new_values'  => $details,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);
    }
}
