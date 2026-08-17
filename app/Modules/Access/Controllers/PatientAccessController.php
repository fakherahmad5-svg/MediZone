<?php

namespace App\Modules\Access\Controllers;

use App\Core\Enums\AccessStatus;
use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\BaseController;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\PatientDoctorAccess;
use App\Modules\Access\Requests\RevokeAccessRequest;
use App\Modules\Access\Resources\PatientDoctorAccessResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PatientAccessController
 *
 * مكان الملف: app/Modules/Access/Controllers/PatientAccessController.php
 * الحالة: [NEW - Phase 7]
 *
 * ⚠️ هذا **ليس** حظراً دائماً لطبيب (لا يمنع حجوزات مستقبلية). هو
 * سحب وصول عن مواعيد **حالية نشطة** فقط — القرار المُتَّفق عليه
 * صراحة. أي حجز جديد لاحقاً مع نفس الطبيب يُنشئ صف وصول جديد
 * status=active من الصفر، غير متأثر بأي سحب سابق.
 *
 * محمي بصلاحية manage_own_access_grants (Phase 1، أُعيد استخدامها —
 * الاسم يطابق الوظيفة الفعلية الآن تماماً).
 *
 * Routes [auth:sanctum, role:patient]:
 *   GET  /patient/access                          → index()            — قائمة الوصول النشط حالياً (شفافية)
 *   POST /patient/access/appointments/{id}/revoke  → revokeAppointment() — سحب عن موعد محدَّد
 *   POST /patient/access/doctors/{id}/revoke        → revokeDoctor()      — سحب عن كل المواعيد النشطة مع طبيب
 */
class PatientAccessController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $patient = $this->patientFor($request->user());

        $access = PatientDoctorAccess::where('patient_id', $patient->id)
            ->where('status', AccessStatus::Active->value)
            ->with(['doctor.user:id,first_name,last_name', 'appointment.slot'])
            ->latest('id')
            ->get();

        return $this->successResponse(
            PatientDoctorAccessResource::collection($access)->resolve($request),
            'Active doctor access retrieved successfully.'
        );
    }

    public function revokeAppointment(RevokeAccessRequest $request, int $appointmentId): JsonResponse
    {
        $this->ensurePermission($request);
        $patient = $this->patientFor($request->user());

        $access = PatientDoctorAccess::where('appointment_id', $appointmentId)
            ->where('patient_id', $patient->id)
            ->where('status', AccessStatus::Active->value)
            ->first();

        if (! $access) {
            throw new NotFoundException('No active access grant found for this appointment.');
        }

        $access->update([
            'status'        => AccessStatus::Revoked->value,
            'revoked_at'    => now(),
            'revoked_by'    => $request->user()->id,
            'revoke_reason' => $request->validated('reason') ?? 'Revoked by patient.',
        ]);

        return $this->successResponse(
            new PatientDoctorAccessResource($access->fresh()),
            'Access revoked for this appointment.'
        );
    }

    public function revokeDoctor(RevokeAccessRequest $request, int $doctorId): JsonResponse
    {
        $this->ensurePermission($request);
        $patient = $this->patientFor($request->user());

        $doctor = Doctor::find($doctorId);

        if (! $doctor) {
            throw new NotFoundException('Doctor not found.');
        }

        $reason = $request->validated('reason') ?? 'Revoked by patient.';

        $count = PatientDoctorAccess::where('patient_id', $patient->id)
            ->where('doctor_id', $doctor->id)
            ->where('status', AccessStatus::Active->value)
            ->update([
                'status'        => AccessStatus::Revoked->value,
                'revoked_at'    => now(),
                'revoked_by'    => $request->user()->id,
                'revoke_reason' => $reason,
            ]);

        return $this->successResponse(
            ['revoked_count' => $count],
            'Access revoked for all currently active appointments with this doctor.'
        );
    }

    // ─────────────────────────────────────────────────────────────

    private function ensurePermission(Request $request): void
    {
        if (! $request->user()->hasPermission('manage_own_access_grants')) {
            throw new AuthorizationException('You are not authorized to manage access grants.');
        }
    }

    private function patientFor(\App\Models\User $user): \App\Models\Patient
    {
        $patient = $user->patient;

        if (! $patient) {
            throw new NotFoundException('Patient profile not found for this account.');
        }

        return $patient;
    }
}
