<?php

namespace App\Modules\Administration\Services;

use App\Core\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorReport;
use App\Models\User;
use App\Modules\DoctorEngagement\Services\DoctorReviewService;
use Illuminate\Pagination\LengthAwarePaginator;

class AnalyticsService
{
    public function __construct(private readonly DoctorReviewService $reviews)
    {
    }


    public function dashboard(): array
    {
        return [
            'appointments'    => $this->appointmentStats(null, null, null),
            'reports'         => $this->reportsSummary(),
            'user_engagement' => $this->userEngagement(now()->subDays(30)->toDateString(), null),
        ];
    }


    public function appointmentStats(?string $from, ?string $to, ?int $clinicId): array
    {
        $base = Appointment::query()
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId));

        $byStatus = (clone $base)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total'     => (int) $byStatus->sum(),
            'by_status' => $byStatus,
        ];
    }


    public function doctorPerformance(int $perPage = 20): LengthAwarePaginator
    {
        $paginator = Doctor::query()
            ->withCount(['appointments as completed_appointments_count' => function ($q) {
                $q->where('status', AppointmentStatus::Completed->value);
            }])
            ->with('user:id,first_name,last_name')
            ->orderByDesc('completed_appointments_count')
            ->paginate($perPage);

        return $paginator->through(function (Doctor $doctor) {
            $reviewSummary = $this->reviews->summaryForDoctor($doctor->id);

            return [
                'doctor_id'              => $doctor->id,
                'name'                   => trim("{$doctor->user?->first_name} {$doctor->user?->last_name}"),
                'completed_appointments' => $doctor->completed_appointments_count,
                'average_rating'         => $reviewSummary['average_rating'],
                'total_reviews'          => $reviewSummary['total_reviews'],
            ];
        });
    }


    public function userEngagement(?string $from, ?string $to): array
    {
        $base = User::query()
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to));

        return [
            'new_patients'       => (clone $base)->whereHas('patient')->count(),
            'new_doctors'        => (clone $base)->whereHas('doctor')->count(),
            'new_receptionists'  => (clone $base)->whereHas('receptionist')->count(),
        ];
    }


    public function reportsSummary(): array
    {
        $byStatus = DoctorReport::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total'         => (int) $byStatus->sum(),
            'by_status'     => $byStatus,
            'pending_count' => (int) ($byStatus['pending'] ?? 0),
        ];
    }
}
