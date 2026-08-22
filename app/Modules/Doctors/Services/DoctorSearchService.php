<?php

namespace App\Modules\Doctors\Services;

use App\Core\Enums\ClinicStatus;
use App\Core\Enums\DoctorVerificationStatus;
use App\Core\Exceptions\NotFoundException;
use App\Models\Doctor;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;


class DoctorSearchService
{
    public function search(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Doctor::query()
            ->with(['user:id,first_name,last_name,gender', 'profile', 'departments', 'clinics'])
            ->where('verification_status', DoctorVerificationStatus::Verified->value)
            ->whereHas('clinics', fn ($q) => $q->where('status', ClinicStatus::Active->value))
            ->whereHas('scheduleConfigs', fn ($q) => $q->where('is_active', true));

        $this->applyDepartmentFilter($query, $filters);
        $this->applyClinicFilter($query, $filters);
        $this->applyNameFilter($query, $filters);
        $this->applyLocationFilter($query, $filters);
        $this->applyExperienceFilter($query, $filters);
        $this->applyPriceFilter($query, $filters);
        $this->applyGenderFilter($query, $filters);
        $this->applyAvailabilityFilter($query, $filters);
        $this->applyTimeSlotFilter($query, $filters);
        $this->applySort($query, $filters);

        return $query->paginate($perPage);
    }

    public function publicProfile(int $doctorId): Doctor
    {
        $doctor = Doctor::query()
            ->with(['user:id,first_name,last_name,gender', 'profile', 'departments', 'clinics'])
            ->where('verification_status', DoctorVerificationStatus::Verified->value)
            ->whereHas('clinics', fn ($q) => $q->where('status', ClinicStatus::Active->value))
            ->find($doctorId);

        if (! $doctor) {
            throw new NotFoundException('Doctor not found or not currently available.');
        }

        return $doctor;
    }

    protected function applyDepartmentFilter(Builder $query, array $filters): void
    {
        $query->when(
            ! empty($filters['department_id']),
            fn ($q) => $q->whereHas(
                'departments',
                fn ($dq) => $dq->where('departments.id', $filters['department_id'])
            )
        );
    }

    protected function applyClinicFilter(Builder $query, array $filters): void
    {
        $query->when(
            ! empty($filters['clinic_id']),
            fn ($q) => $q->whereHas(
                'clinics',
                fn ($cq) => $cq->where('clinics.id', $filters['clinic_id'])
            )
        );
    }

    protected function applyNameFilter(Builder $query, array $filters): void
    {
        $query->when(
            ! empty($filters['name']),
            fn ($q) => $q->whereHas(
                'user',
                fn ($uq) => $uq->where(
                    fn ($sub) => $sub
                        ->where('first_name', 'like', "%{$filters['name']}%")
                        ->orWhere('last_name', 'like', "%{$filters['name']}%")
                )
            )
        );
    }

    /**
     * "Near me (GPS)" - keeps doctors who have at least one active clinic
     * within radius_km of the given lat/lng (Haversine formula, km).
     * City/Area selection is deferred - GPS only for now.
     */
    protected function applyLocationFilter(Builder $query, array $filters): void
    {
        if (empty($filters['latitude']) || empty($filters['longitude'])) {
            return;
        }

        $lat    = (float) $filters['latitude'];
        $lng    = (float) $filters['longitude'];
        $radius = (float) ($filters['radius_km'] ?? 25);

        $query->whereHas('clinics', function ($q) use ($lat, $lng, $radius) {
            $q->whereNotNull('clinics.latitude')
                ->whereNotNull('clinics.longitude')
                ->whereRaw(
                    '(6371 * acos(
                        cos(radians(?)) * cos(radians(clinics.latitude))
                        * cos(radians(clinics.longitude) - radians(?))
                        + sin(radians(?)) * sin(radians(clinics.latitude))
                    )) <= ?',
                    [$lat, $lng, $lat, $radius]
                );
        });
    }

    /**
     * Experience filter derived from doctors.practice_start_date.
     * experience_min = at least N years in practice.
     * experience_max = at most N years in practice.
     */
    protected function applyExperienceFilter(Builder $query, array $filters): void
    {
        $query
            ->when(
                isset($filters['experience_min']),
                fn ($q) => $q->where('practice_start_date', '<=', now()->subYears((int) $filters['experience_min']))
            )
            ->when(
                isset($filters['experience_max']),
                fn ($q) => $q->where('practice_start_date', '>=', now()->subYears((int) $filters['experience_max']))
            );
    }

    /**
     * Consultation price filter.
     * ASSUMPTION: consultation_fee lives on the doctor_clinics pivot table.
     * Adjust the table/column name below if it differs in your schema.
     */
    protected function applyPriceFilter(Builder $query, array $filters): void
    {
        $hasMin = isset($filters['price_min']);
        $hasMax = isset($filters['price_max']);

        $query->when(
            $hasMin || $hasMax,
            function ($q) use ($filters, $hasMin, $hasMax) {
                // A doctor matches if EITHER an active clinic's fee is in range, OR
                // (when no clinic fee applies) their online consultation fee is in
                // range - this mirrors the fallback the frontend uses to decide
                // which fee to actually display for a doctor.
                $q->where(function ($outer) use ($filters, $hasMin, $hasMax) {
                    $outer->whereHas('clinics', function ($cq) use ($filters, $hasMin, $hasMax) {
                        if ($hasMin) {
                            $cq->where('doctor_clinics.consultation_fee', '>=', $filters['price_min']);
                        }
                        if ($hasMax) {
                            $cq->where('doctor_clinics.consultation_fee', '<=', $filters['price_max']);
                        }
                    })->orWhereHas('profile', function ($pq) use ($filters, $hasMin, $hasMax) {
                        $pq->whereNotNull('online_consultation_fee');
                        if ($hasMin) {
                            $pq->where('online_consultation_fee', '>=', $filters['price_min']);
                        }
                        if ($hasMax) {
                            $pq->where('online_consultation_fee', '<=', $filters['price_max']);
                        }
                    });
                });
            }
        );
    }

    /**
     * Doctor gender - matches users.gender via the user relation.
     */
    protected function applyGenderFilter(Builder $query, array $filters): void
    {
        $query->when(
            ! empty($filters['gender']),
            fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('gender', $filters['gender']))
        );
    }

    /**
     * "Availability on App" - checks the doctor has an active schedule
     * (config + active weekday + at least one active session) on the
     * relevant date(s), and isn't on vacation that day.
     *
     * NOTE: this only checks the recurring schedule exists - it does not
     * exclude fully-booked days since there's no Appointment table in
     * what I was given. Let me know if that needs to be layered in.
     */
    protected function applyAvailabilityFilter(Builder $query, array $filters): void
    {
        if (empty($filters['availability'])) {
            return;
        }

        $dates = match ($filters['availability']) {
            'today'     => collect([now()->startOfDay()]),
            'tomorrow'  => collect([now()->addDay()->startOfDay()]),
            'this_week' => collect(range(0, 6))->map(fn ($i) => now()->addDays($i)->startOfDay()),
            'custom'    => collect([Carbon::parse($filters['custom_date'])->startOfDay()]),
            default     => collect(),
        };

        if ($dates->isEmpty()) {
            return;
        }

        $daysOfWeek = $dates->map(fn ($d) => $d->dayOfWeek)->unique()->values();

        $query->whereHas('scheduleConfigs', function ($q) use ($dates, $daysOfWeek) {
            $q->where('is_active', true)
                ->where(function ($vacationQ) use ($dates) {
                    // At least one of the candidate dates must fall outside vacation range.
                    foreach ($dates as $date) {
                        $vacationQ->orWhere(function ($d) use ($date) {
                            $d->whereNull('vacation_start_date')
                                ->orWhereNull('vacation_end_date')
                                ->orWhere('vacation_start_date', '>', $date)
                                ->orWhere('vacation_end_date', '<', $date);
                        });
                    }
                })
                ->whereHas('days', function ($dq) use ($daysOfWeek) {
                    $dq->where('is_active', true)
                        ->whereIn('day_of_week', $daysOfWeek)
                        ->whereHas('sessions', fn ($sq) => $sq->where('is_active', true));
                });
        });
    }

    /**
     * Time Slot filter (Morning / Afternoon / Evening) - matches
     * schedule_sessions.session_type directly.
     */
    protected function applyTimeSlotFilter(Builder $query, array $filters): void
    {
        $query->when(
            ! empty($filters['time_slot']),
            fn ($q) => $q->whereHas(
                'scheduleConfigs.days.sessions',
                fn ($sq) => $sq->whereIn('session_type', $filters['time_slot'])
                    ->where('is_active', true)
            )
        );
    }

    /**
     * Sorting: Best Match (default), Fee asc/desc, Experience.
     * "reviews" sort is intentionally not implemented yet.
     */
    protected function applySort(Builder $query, array $filters): void
    {
        match ($filters['sort'] ?? 'best_match') {
            'fee_asc'    => $this->orderByFee($query, 'asc'),
            'fee_desc'   => $this->orderByFee($query, 'desc'),
            'experience' => $query->orderBy('practice_start_date', 'asc'), // earlier start date = more experience
            default      => null,
        };
    }

    /**
     * Orders by the doctor's lowest consultation_fee across their clinics,
     * via a correlated subquery (avoids duplicate rows from joining the
     * pivot table directly).
     */
    protected function orderByFee(Builder $query, string $direction): void
    {
        $query->addSelect([
            'sort_fee' => DB::table('doctor_clinics')
                ->selectRaw('MIN(consultation_fee)')
                ->whereColumn('doctor_clinics.doctor_id', 'doctors.id'),
        ])->orderBy('sort_fee', $direction);
    }
}
