<?php

namespace App\Core\Services;

use App\Core\Enums\AccessAction;
use App\Core\Enums\AccessStatus;
use App\Core\Enums\AccessType;
use App\Core\Enums\AppointmentStatus;
use App\Models\Doctor;
use App\Models\MedicalRecordAccessLog;
use App\Models\Patient;
use App\Models\PatientDoctorAccess;
use App\Models\User;

/**
 * AccessGuard
 *
 * مكان الملف: app/Core/Services/AccessGuard.php
 * الحالة: [NEW - Phase 7]
 *
 * نقطة الحقيقة الوحيدة لسؤال "هل هذا الطبيب يملك وصولاً لسجل هذا
 * المريض الآن، وبأي مستوى؟" — تُستخدَم من PatientRecordPolicy، وأي
 * وحدة مستقبلية (Medical doctor-facing endpoints, Encounters...).
 *
 * ═══════════════════════════════════════════════════════════════
 *  القرار المعماري الأهم: حساب لحظي، لا Job مجدوَل
 * ═══════════════════════════════════════════════════════════════
 * صف patient_doctor_access لا يُخزِّن "مستوى الوصول الحالي" كقيمة
 * جامدة — فقط "هل هذا الموعد أنتج وصولاً، وهل سُحِب يدوياً أو انتهى
 * طبيعياً". المستوى الفعلي (none/read_only/full) يُحسَب من دمج ذلك
 * مع حالة الموعد اللحظية والوقت الحالي، في كل استدعاء. هذا يتجنَّب
 * الحاجة لأي Job يُحدِّث الصفوف عند عبور نافذة الـ 48 ساعة —
 * الحساب لا يتقادم أبداً لأنه لا يُخزَّن أصلاً.
 *
 * ═══════════════════════════════════════════════════════════════
 *  دفاع مزدوج (Defense in Depth)
 * ═══════════════════════════════════════════════════════════════
 * الاستعلام يتحقق من status=active على الصف **و** من أن حالة الموعد
 * المرتبط ليست نهائية سلبية (cancelled/no_show/completed) معاً. لو
 * لسبب ما فشل تحديث الصف عند الإلغاء (خطأ برمجي مستقبلي)، لا يزال
 * فحص حالة الموعد نفسه يمنع تسريب الوصول — مبرَّر بحساسية البيانات
 * الطبية (NFR-S04/NFR-S08).
 */
class AccessGuard extends BaseService
{
    /**
     * نافذة القراءة قبل الموعد — 48 ساعة (قابلة للتعديل من هنا فقط).
     */
    private const READ_ONLY_WINDOW_HOURS = 48;

    /**
     * المستوى الحالي: null = لا وصول، وإلا AccessType (ReadOnly|Full).
     */
    public function levelFor(Doctor $doctor, Patient $patient): ?AccessType
    {
        $access = $this->resolveActiveAccess($doctor, $patient);

        if (! $access) {
            return null;
        }

        $appointment = $access->appointment;

        if (in_array($appointment->status, [AppointmentStatus::CheckedIn, AppointmentStatus::InProgress], true)) {
            return AccessType::Full;
        }

        if ($appointment->status === AppointmentStatus::Scheduled) {
            $windowStart = $appointment->slot?->starts_at?->copy()->subHours(self::READ_ONLY_WINDOW_HOURS);

            if ($windowStart && now()->greaterThanOrEqualTo($windowStart)) {
                return AccessType::ReadOnly;
            }
        }

        return null;
    }

    /**
     * فحص إجراء محدَّد (وليس فقط مستوى عام) — يميّز بين أفعال القراءة
     * وأفعال الكتابة السريرية عبر AccessAction::requiresFullAccess()
     * (Phase 1 enum). جاهزة لوحدات مستقبلية (Encounters) دون تعديل هنا.
     */
    public function canPerform(Doctor $doctor, Patient $patient, AccessAction $action): bool
    {
        $level = $this->levelFor($doctor, $patient);

        if ($level === null) {
            return false;
        }

        return $action->requiresFullAccess() ? $level === AccessType::Full : true;
    }

    /**
     * الصف الفعّال (إن وُجد) — تُستخدَم داخلياً وأيضاً مُتاحة للوحدات
     * الأخرى (لتسجيل access log دون استعلام مكرِّر).
     */
    public function resolveActiveAccess(Doctor $doctor, Patient $patient): ?PatientDoctorAccess
    {
        return PatientDoctorAccess::where('doctor_id', $doctor->id)
            ->where('patient_id', $patient->id)
            ->where('status', AccessStatus::Active->value)
            ->whereHas('appointment', function ($q) {
                $q->whereNotIn('status', [
                    AppointmentStatus::Cancelled->value,
                    AppointmentStatus::NoShow->value,
                    AppointmentStatus::Completed->value,
                ]);
            })
            ->with('appointment.slot')
            ->latest('id')
            ->first();
    }

    /**
     * [جاهزة للاستخدام المستقبلي] تسجيل قراءة/كتابة فعلية في
     * medical_record_access_logs — لم تُربَط بأي controller بعد (لا
     * توجد endpoints للطبيب على السجل الطبي حتى الآن)، لكن معدَّة
     * لتُستدعى مباشرة عند بناء تلك الـ endpoints دون تعديل هنا.
     */
    public function logAccess(PatientDoctorAccess $access, AccessAction $action, ?string $entityType = null, ?int $entityId = null): void
    {
        MedicalRecordAccessLog::create([
            'access_id'   => $access->id,
            'action'      => $action->value,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'ip_address'  => request()->ip(),
            'accessed_at' => now(),
        ]);
    }
}
