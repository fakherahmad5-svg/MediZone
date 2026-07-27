<?php

namespace App\Modules\Clinics\Services;

use App\Core\Enums\ClinicStatus;
use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;


class ClinicService extends BaseService
{
    // ─────────────────────────────────────────────────────────────
    //  Public / Search
    // ─────────────────────────────────────────────────────────────

    public function activeList(?string $search = null): Collection
    {
        return Clinic::query()
            ->where('status', ClinicStatus::Active->value)
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->get();
    }

    public function findActiveOrFail(int $id): Clinic
    {
        $clinic = Clinic::where('status', ClinicStatus::Active->value)->find($id);

        if (! $clinic) {
            throw new NotFoundException('Clinic not found or not currently active.');
        }

        return $clinic;
    }

    public function findOrFail(int $id): Clinic
    {
        $clinic = Clinic::find($id);

        if (! $clinic) {
            throw new NotFoundException('Clinic not found.');
        }

        return $clinic;
    }

    // ─────────────────────────────────────────────────────────────
    //  Admin Management
    // ─────────────────────────────────────────────────────────────

    /**
     * @param array{status?: string, search?: string} $filters
     */
    public function paginateForAdmin(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Clinic::query()
            ->with(['owner:id,first_name,last_name,email', 'departments:id,name'])
            ->when(
                ! empty($filters['status']),
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when(
                ! empty($filters['search']),
                fn ($q) => $q->where('name', 'like', "%{$filters['search']}%")
            )
            ->latest('id')
            ->paginate($perPage);
    }


    public function createByAdmin(array $data): Clinic
    {
        return $this->transaction(function () use ($data) {
            return Clinic::create([
                'name'      => $data['name'],
                'phone'     => $data['phone'] ?? null,
                //'email'     => $data['email'] ?? null,
                'address'   => $data['address'] ?? null,
                'latitude'  => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'status'    => ClinicStatus::Active->value,
            ]);
        });
    }

    public function update(Clinic $clinic, array $data): Clinic
    {

        return $this->transaction(function () use ($clinic, $data) {

            $clinic->update([
                'name'      => $data['name'] ?? $clinic->name,
                'phone'     => $data['phone'] ?? $clinic->phone,
                //'email'     => $data['email'] ?? $clinic->email,
                'address'   => $data['address'] ?? $clinic->address,
                'latitude'  => $data['latitude'] ?? $clinic->latitude,
                'longitude' => $data['longitude'] ?? $clinic->longitude,
            ]);

            return $clinic->fresh();
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  Approval Workflow
    // ─────────────────────────────────────────────────────────────

    public function pendingList(int $perPage = 15): LengthAwarePaginator
    {
        return Clinic::query()
            ->where('status', ClinicStatus::Pending->value)
            ->with('owner:id,first_name,last_name,email')
            ->oldest('id')
            ->paginate($perPage);
    }


    public function approve(Clinic $clinic, User $admin): Clinic
    {
        return $this->transaction(function () use ($clinic, $admin) {
            $this->ensureIsPending($clinic);

            $clinic->update([
                'status'       => ClinicStatus::Active->value,
            ]);

            $this->writeAudit($admin, $clinic, 'clinic_approved');

            return $clinic->fresh();
        });
    }

    public function reject(Clinic $clinic, User $admin, string $reason): Clinic
    {
        return $this->transaction(function () use ($clinic, $admin, $reason) {
            $this->ensureIsPending($clinic);

            $clinic->update([
                'status'       => ClinicStatus::Rejected->value,

            ]);

            $this->writeAudit($admin, $clinic, 'clinic_rejected', ['reason' => $reason]);

            return $clinic->fresh();
        });
    }

    public function suspend(Clinic $clinic, User $admin, string $reason): Clinic
    {
        return $this->transaction(function () use ($clinic, $admin, $reason) {
            if (! $clinic->isActive()) {
                throw new BusinessException('Only active clinics can be suspended.');
            }

            $clinic->update([
                'status'       => ClinicStatus::Suspended->value,

            ]);

            $this->writeAudit($admin, $clinic, 'clinic_suspended', ['reason' => $reason]);

            return $clinic->fresh();
        });
    }

    public function reactivate(Clinic $clinic, User $admin): Clinic
    {
        return $this->transaction(function () use ($clinic, $admin) {
            if (! $clinic->isSuspended()) {
                throw new BusinessException('Only suspended clinics can be reactivated.');
            }

            $clinic->update([
                'status'       => ClinicStatus::Active->value,

            ]);

            $this->writeAudit($admin, $clinic, 'clinic_reactivated');

            return $clinic->fresh();
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  Department Attachment
    // ─────────────────────────────────────────────────────────────

    public function syncDepartments(Clinic $clinic, array $departmentIds): Clinic
    {
        return $this->transaction(function () use ($clinic, $departmentIds) {
            $validIds = Department::whereIn('id', $departmentIds)->pluck('id')->all();

            $clinic->departments()->sync($validIds);

            return $clinic->fresh('departments');
        });
    }

    // ─────────────────────────────────────────────────────────────
    //  Private Helpers
    // ─────────────────────────────────────────────────────────────


    private function ensureIsPending(Clinic $clinic): void
    {
        if (! $clinic->isPending()) {
            throw new BusinessException('This clinic is not awaiting review.');
        }
    }


    private function writeAudit(User $admin, Clinic $clinic, string $action, array $details = []): void
    {
        AuditLog::query()->create([
            'user_id'     => $admin->id,
            'clinic_id'   => $clinic->id,
            'action'      => $action,
            'entity_type' => Clinic::class,
            'entity_id'   => $clinic->id,
            'old_values'  => null,
            'new_values'  => $details,
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);
    }
}
