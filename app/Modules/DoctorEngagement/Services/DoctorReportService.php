<?php

namespace App\Modules\DoctorEngagement\Services;

use App\Core\Enums\ReportStatus;
use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\BaseService;
use App\Models\AuditLog;
use App\Models\Doctor;
use App\Models\DoctorReport;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;


class DoctorReportService extends BaseService
{
    public function submit(User $patientUser, int $doctorId, string $category, string $description, ?int $encounterId): DoctorReport
    {
        $patient = $this->patientFor($patientUser);

        $doctor = Doctor::find($doctorId);

        if (! $doctor) {
            throw new NotFoundException('Doctor not found.');
        }

        $encounter = null;

        if ($encounterId) {
            $encounter = Encounter::with('appointment')->find($encounterId);

            if (! $encounter
                || $encounter->appointment?->patient_id !== $patient->id
                || $encounter->appointment?->doctor_id !== $doctorId) {
                throw new NotFoundException('Encounter not found for this patient and doctor.');
            }
        }

        return $this->transaction(function () use ($patient, $doctor, $category, $description, $encounter) {
            return DoctorReport::create([
                'clinic_id'    => $encounter?->appointment?->clinic_id,
                'patient_id'   => $patient->id,
                'doctor_id'    => $doctor->id,
                'encounter_id' => $encounter?->id,
                'category'     => $category,
                'description'  => $description,
                'status'       => ReportStatus::Pending->value,
            ]);
        });
    }

    public function listForPatient(User $patientUser, int $perPage = 15)
    {
        $patient = $this->patientFor($patientUser);

        return DoctorReport::where('patient_id', $patient->id)
            ->with('doctor.user:id,first_name,last_name')
            ->latest('id')
            ->paginate($perPage);
    }


    public function addPatientFeedback(User $patientUser, int $reportId, string $feedback): DoctorReport
    {
        $patient = $this->patientFor($patientUser);

        $report = DoctorReport::find($reportId);

        if (! $report || $report->patient_id !== $patient->id) {
            throw new NotFoundException('Report not found.');
        }

        if (! in_array($report->status, [ReportStatus::Resolved->value, ReportStatus::ActionTaken->value, ReportStatus::Dismissed->value], true)) {
            throw new BusinessException('You can only add feedback after the report has been closed.');
        }

        $report->update(['patient_feedback' => $feedback]);

        return $report->fresh();
    }

    // ─── Admin (UC-A04) ─────────────────────────────────────────


    public function listForAdmin(array $filters, int $perPage = 20)
    {
        return DoctorReport::query()
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['category']), fn ($q) => $q->where('category', $filters['category']))
            ->when(! empty($filters['doctor_id']), fn ($q) => $q->where('doctor_id', $filters['doctor_id']))
            ->with(['patient.user:id,first_name,last_name', 'doctor.user:id,first_name,last_name'])
            ->latest('id')
            ->paginate($perPage);
    }

    public function markUnderReview(User $admin, DoctorReport $report): DoctorReport
    {
        $this->ensureAdmin($admin);

        return $this->transaction(function () use ($admin, $report) {
            $oldStatus = $report->status;

            $report->update([
                'status'      => ReportStatus::UnderReview->value,
                'admin_id'    => $admin->id,
                'reviewed_at' => now(),
            ]);

            $this->auditLog($admin, $report, 'report_under_review', $oldStatus, ReportStatus::UnderReview->value);

            return $report->fresh();
        });
    }


    public function resolve(User $admin, DoctorReport $report, ReportStatus $finalStatus, ?string $adminAction, ?string $adminNotes): DoctorReport
    {
        $this->ensureAdmin($admin);

        if (! in_array($finalStatus, [ReportStatus::ActionTaken, ReportStatus::Resolved, ReportStatus::Dismissed], true)) {
            throw new BusinessException('Invalid final status for closing a report.');
        }

        return $this->transaction(function () use ($admin, $report, $finalStatus, $adminAction, $adminNotes) {
            $oldStatus = $report->status;

            $report->update([
                'status'        => $finalStatus->value,
                'admin_id'      => $admin->id,
                'admin_action'  => $adminAction,
                'admin_notes'   => $adminNotes,
                'resolved_at'   => now(),
            ]);

            $this->auditLog($admin, $report, 'report_resolved', $oldStatus, $finalStatus->value);

            return $report->fresh();
        });
    }

    // ─────────────────────────────────────────────────────────────

    private function ensureAdmin(User $user): void
    {
        if (! $user->isSuperAdmin()) {
            throw new AuthorizationException('You are not authorized to manage reports.');
        }
    }

    private function auditLog(User $admin, DoctorReport $report, string $action, $oldStatus, string $newStatus): void
    {
        AuditLog::create([
            'user_id'     => $admin->id,
            'action'      => $action,
            'entity_type' => DoctorReport::class,
            'entity_id'   => $report->id,
            'old_values'  => ['status' => is_string($oldStatus) ? $oldStatus : $oldStatus?->value],
            'new_values'  => ['status' => $newStatus],
            'ip_address'  => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ]);
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
