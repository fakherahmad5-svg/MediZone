<?php

namespace App\Modules\DoctorEngagement\Services;

use App\Core\Enums\AppointmentStatus;
use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorReview;
use App\Models\Patient;
use App\Models\User;

/**
 * DoctorReviewService
 *
 * مكان الملف: app/Modules/DoctorEngagement/Services/DoctorReviewService.php
 * الحالة: [NEW - Phase 10]
 *
 * قراران مُتَّفق عليهما:
 *   1. التقييم مسموح فقط بعد موعد completed واحد على الأقل بين
 *      المريض وهذا الطبيب (UC-P09 precondition).
 *   2. مراجعة واحدة لكل (patient_id, doctor_id) — إرسال ثانٍ يُحدِّث
 *      نفس الصف (updateOrCreate)، لا يُنشئ صفاً جديداً. محمي أيضاً
 *      بـ unique constraint على مستوى القاعدة (migration مرفَق).
 *
 * clinic_id يُشتَق من أحدث موعد completed بين الاثنين — هو من منح
 * الأهلية للتقييم أصلاً (قرار مُتَّفق عليه).
 */
class DoctorReviewService extends BaseService
{
    public function submit(User $patientUser, int $doctorId, int $rating, ?string $comment): DoctorReview
    {
        $patient = $this->patientFor($patientUser);

        if (! Doctor::find($doctorId)) {
            throw new NotFoundException('Doctor not found.');
        }

        $latestCompleted = $this->latestCompletedAppointment($patient->id, $doctorId);

        if (! $latestCompleted) {
            throw new BusinessException(
                'You can only review a doctor after completing at least one appointment with them.'
            );
        }

        return $this->transaction(function () use ($patient, $doctorId, $rating, $comment, $latestCompleted) {
            return DoctorReview::updateOrCreate(
                ['patient_id' => $patient->id, 'doctor_id' => $doctorId],
                [
                    'clinic_id' => $latestCompleted->clinic_id,
                    'rating'    => $rating,
                    'comment'   => $comment,
                ]
            );
        });
    }

    public function myReview(User $patientUser, int $doctorId): ?DoctorReview
    {
        $patient = $this->patientFor($patientUser);

        return DoctorReview::where('patient_id', $patient->id)
            ->where('doctor_id', $doctorId)
            ->first();
    }

    public function delete(User $patientUser, int $doctorId): void
    {
        $patient = $this->patientFor($patientUser);

        DoctorReview::where('patient_id', $patient->id)
            ->where('doctor_id', $doctorId)
            ->delete();
    }

    /**
     * عام — لصفحة بروفايل الطبيب (UC-P02).
     */
    public function forDoctor(int $doctorId, int $perPage = 15)
    {
        return DoctorReview::where('doctor_id', $doctorId)
            ->with('patient.user:id,first_name,last_name')
            ->latest('id')
            ->paginate($perPage);
    }

    public function summaryForDoctor(int $doctorId): array
    {
        $stats = DoctorReview::where('doctor_id', $doctorId)
            ->selectRaw('COUNT(*) as total, AVG(rating) as average')
            ->first();

        return [
            'total_reviews'  => (int) $stats->total,
            'average_rating' => $stats->total > 0 ? round((float) $stats->average, 2) : null,
        ];
    }

    // ─────────────────────────────────────────────────────────────

    private function latestCompletedAppointment(int $patientId, int $doctorId): ?Appointment
    {
        return Appointment::where('patient_id', $patientId)
            ->where('doctor_id', $doctorId)
            ->where('status', AppointmentStatus::Completed->value)
            ->latest('id')
            ->first();
    }

    private function patientFor(User $user): Patient
    {
        $patient = $user->patient;

        if (! $patient) {
            throw new NotFoundException('Patient profile not found for this account.');
        }

        return $patient;
    }
}
