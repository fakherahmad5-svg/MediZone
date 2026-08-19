<?php

namespace App\Modules\Encounters\Services;

use App\Core\Enums\AccessAction;
use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\MedicationSource;
use App\Core\Enums\MedicationStatus;
use App\Core\Exceptions\AuthorizationException;
use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\NotFoundException;
use App\Core\Services\AccessGuard;
use App\Core\Services\BaseService;
use App\Models\Appointment;
use App\Models\ClinicalNote;
use App\Models\Diagnosis;
use App\Models\Doctor;
use App\Models\Encounter;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;

/**
 * EncounterService
 *
 * مكان الملف: app/Modules/Encounters/Services/EncounterService.php
 * الحالة: [NEW - Phase 8]
 *
 * يرث من BaseService (Phase 0) لـ transaction()/logError().
 *
 * ═══════════════════════════════════════════════════════════════
 *  ⚠️ قرار نطاق مُتَّفق عليه: نافذة الكتابة in_progress فقط
 * ═══════════════════════════════════════════════════════════════
 * AccessGuard::canPerform() (Phase 7) يسمح بـ Full أثناء checked_in
 * أو in_progress معاً. هذا الصنف يُضيف قيداً أضيق فوق ذلك عمداً —
 * لا تُقبَل كتابة سريرية (ملاحظة/تشخيص/وصفة) إلا والموعد in_progress
 * تحديداً (الفحص جارٍ فعلياً). القيد هنا، وليس تعديلاً على AccessGuard
 * نفسه، لأنه قرار نطاق خاص بـ Encounters وليس قاعدة وصول عامة.
 *
 * ═══════════════════════════════════════════════════════════════
 *  ⚠️ نقطة التكامل الأهم: الوصفة تُنشئ صف Medication تلقائياً
 * ═══════════════════════════════════════════════════════════════
 * migration الخاص بـ prescription_items (Phase 4) أضاف فعلياً FK
 * على medications.prescription_item_id بانتظار هذه اللحظة تحديداً.
 * كل عنصر وصفة يُنشئ صف Medication مقابلاً بـ
 * source=MedicationSource::Prescribed — فيظهر تلقائياً ضمن
 * GET /patient/medical-record/medications للمريض، ويبقى محمياً من
 * تعديل المريض عبر Medication::isEditableByPatient() الموجودة أصلاً
 * (تتحقق source===SelfReported فقط).
 *
 * ⚠️ migration الفعلي لـ medications تأكَّد لاحقاً (بعد التسليم
 * الأول لهذا الملف) — dosage/frequency/route/notes تُنسَخ الآن من
 * عنصر الوصفة مباشرة لأنها أعمدة حقيقية على Medication نفسه. route
 * آمن للنسخ لأن StorePrescriptionItemRequest يفرض نفس قيم
 * MedicationRoute (enum حقيقي على medications.route) عند الإدخال —
 * لا يمكن لقيمة غير صالحة الوصول لهذه النقطة أصلاً.
 *
 * ═══════════════════════════════════════════════════════════════
 *  ⚠️ فجوة في AccessAction: لا حالة مخصَّصة لـ"إنشاء تشخيص"
 * ═══════════════════════════════════════════════════════════════
 * AccessAction (Phase 1/7) يملك CreateEncounter/CreateNote/
 * CreatePrescription فقط — بلا CreateDiagnosis. سُجِّلت كتابة
 * التشخيص هنا تحت CreateNote (أقرب تصنيف موجود، كلاهما
 * requiresFullAccess()===true فيُحقَّق الأمان بنفس القوة) — قرار
 * تصنيف واعٍ، وليس خطأً.
 */
class EncounterService extends BaseService
{
    public function addClinicalNote(Appointment $appointment, User $doctorUser, string $content): ClinicalNote
    {
        [$doctor] = $this->ensureCanWrite($appointment, $doctorUser, AccessAction::CreateNote);

        return $this->transaction(function () use ($appointment, $doctor, $doctorUser, $content) {
            $encounter = $this->resolveOrCreateForAppointment($appointment, $doctorUser);

            $note = ClinicalNote::create([
                'encounter_id' => $encounter->id,
                'doctor_id'    => $doctor->id,
                'content'      => $content,
            ]);

            $this->logWrite($doctor, $appointment, AccessAction::CreateNote, ClinicalNote::class, $note->id);

            return $note;
        });
    }

    /**
     * ⚠️ تُسجَّل تحت AccessAction::CreateNote (انظر توثيق الصنف أعلاه —
     * لا يوجد CreateDiagnosis في الـ enum الحالي).
     */
    public function addDiagnosis(Appointment $appointment, User $doctorUser, string $label, ?string $description): Diagnosis
    {
        [$doctor] = $this->ensureCanWrite($appointment, $doctorUser, AccessAction::CreateNote);

        return $this->transaction(function () use ($appointment, $doctor, $doctorUser, $label, $description) {
            $encounter = $this->resolveOrCreateForAppointment($appointment, $doctorUser);

            $diagnosis = Diagnosis::create([
                'encounter_id' => $encounter->id,
                'doctor_id'    => $doctor->id,
                'label'        => $label,
                'description'  => $description,
            ]);

            $this->logWrite($doctor, $appointment, AccessAction::CreateNote, Diagnosis::class, $diagnosis->id);

            return $diagnosis;
        });
    }

    /**
     * إضافة عنصر وصفة — تُنشئ Prescription (firstOrCreate، وصفة واحدة
     * لكل زيارة) ثم العنصر، ثم صف Medication مقابل تلقائياً (انظر
     * توثيق الصنف أعلاه).
     *
     * @param array{dosage?:string,frequency?:string,duration?:string,route?:string,notes?:string} $data
     */
    public function addPrescriptionItem(Appointment $appointment, User $doctorUser, int $drugId, array $data): PrescriptionItem
    {
        [$doctor] = $this->ensureCanWrite($appointment, $doctorUser, AccessAction::CreatePrescription);

        return $this->transaction(function () use ($appointment, $doctor, $doctorUser, $drugId, $data) {
            $encounter = $this->resolveOrCreateForAppointment($appointment, $doctorUser);

            $prescription = Prescription::firstOrCreate(
                ['encounter_id' => $encounter->id],
                ['doctor_id' => $doctor->id]
            );

            $item = PrescriptionItem::create([
                'prescription_id' => $prescription->id,
                'drug_id'         => $drugId,
                'dosage'          => $data['dosage'] ?? null,
                'frequency'       => $data['frequency'] ?? null,
                'duration'        => $data['duration'] ?? null,
                'route'           => $data['route'] ?? null,
                'notes'           => $data['notes'] ?? null,
            ]);

            // [نقطة تكامل حرجة] يظهر تلقائياً في قائمة أدوية المريض.
            // dosage/frequency/route/notes تُنسَخ من عنصر الوصفة —
            // route آمن الآن لأن StorePrescriptionItemRequest يفرض
            // نفس قيم MedicationRoute عند الإدخال (انظر توثيق ذلك
            // الملف)، فلا خطر فشل SQL على enum medications.route.
            Medication::create([
                'patient_record_id'    => $encounter->patient_record_id,
                'drug_id'              => $drugId,
                'prescription_item_id' => $item->id,
                'source'               => MedicationSource::Prescribed->value,
                'status'               => MedicationStatus::Active->value,
                'dosage'               => $item->dosage,
                'frequency'            => $item->frequency,
                'route'                => $item->route,
                'start_date'           => now()->toDateString(),
                'recorded_by'          => $doctorUser->id,
                'notes'                => $item->notes,
            ]);

            $this->logWrite($doctor, $appointment, AccessAction::CreatePrescription, PrescriptionItem::class, $item->id);

            return $item->fresh('drug');
        });
    }

    public function forAppointment(Appointment $appointment, User $doctorUser): ?Encounter
    {
        $this->ensureOwnership($appointment, $doctorUser);

        return Encounter::where('appointment_id', $appointment->id)
            ->with(['clinicalNotes', 'diagnoses', 'prescription.items.drug'])
            ->first();
    }


    public function submit(Appointment $appointment, User $doctorUser, array $notes, array $diagnoses, array $prescriptionItems): Encounter
    {
        if (empty($notes) && empty($diagnoses) && empty($prescriptionItems)) {
            throw new BusinessException('Nothing to submit — provide at least one note, diagnosis, or prescription item.');
        }

        // تحقق واحد يكفي لكل الدفعة — نفس معيار CreateEncounter/
        // CreateNote/CreatePrescription (كلها requiresFullAccess===true).
        [$doctor] = $this->ensureCanWrite($appointment, $doctorUser, AccessAction::CreateEncounter);

        return $this->transaction(function () use ($appointment, $doctor, $doctorUser, $notes, $diagnoses, $prescriptionItems) {
            $encounter = $this->resolveOrCreateForAppointment($appointment, $doctorUser);

            foreach ($notes as $noteData) {
                $note = ClinicalNote::create([
                    'encounter_id' => $encounter->id,
                    'doctor_id'    => $doctor->id,
                    'content'      => $noteData['content'],
                ]);

                $this->logWrite($doctor, $appointment, AccessAction::CreateNote, ClinicalNote::class, $note->id);
            }

            foreach ($diagnoses as $diagnosisData) {
                $diagnosis = Diagnosis::create([
                    'encounter_id' => $encounter->id,
                    'doctor_id'    => $doctor->id,
                    'label'        => $diagnosisData['label'],
                    'description'  => $diagnosisData['description'] ?? null,
                ]);

                // نفس قرار التصنيف الموثَّق أعلاه (لا CreateDiagnosis في AccessAction).
                $this->logWrite($doctor, $appointment, AccessAction::CreateNote, Diagnosis::class, $diagnosis->id);
            }

            if (! empty($prescriptionItems)) {
                $prescription = Prescription::firstOrCreate(
                    ['encounter_id' => $encounter->id],
                    ['doctor_id' => $doctor->id]
                );

                foreach ($prescriptionItems as $itemData) {
                    $item = PrescriptionItem::create([
                        'prescription_id' => $prescription->id,
                        'drug_id'         => $itemData['drug_id'],
                        'dosage'          => $itemData['dosage'] ?? null,
                        'frequency'       => $itemData['frequency'] ?? null,
                        'duration'        => $itemData['duration'] ?? null,
                        'route'           => $itemData['route'] ?? null,
                        'notes'           => $itemData['notes'] ?? null,
                    ]);

                    Medication::create([
                        'patient_record_id'    => $encounter->patient_record_id,
                        'drug_id'              => $itemData['drug_id'],
                        'prescription_item_id' => $item->id,
                        'source'               => MedicationSource::Prescribed->value,
                        'status'               => MedicationStatus::Active->value,
                        'dosage'               => $item->dosage,
                        'frequency'            => $item->frequency,
                        'route'                => $item->route,
                        'start_date'           => now()->toDateString(),
                        'recorded_by'          => $doctorUser->id,
                        'notes'                => $item->notes,
                    ]);

                    $this->logWrite($doctor, $appointment, AccessAction::CreatePrescription, PrescriptionItem::class, $item->id);
                }
            }

            return $encounter->fresh(['clinicalNotes', 'diagnoses', 'prescription.items.drug']);
        });
    }

    // ─────────────────────────────────────────────────────────────

    /**
     * إنشاء الـ Encounter ضمنياً عند أول كتابة — firstOrCreate بنفس
     * نمط PatientObserver/SlotGeneratorService الراسخ. visit_type
     * يُشتَق تلقائياً من appointment.encounter_type (قرار مُتَّفق
     * عليه: لا إدخال يدوي، يمنع تضارب البيانات بين الحقلين).
     */
    private function resolveOrCreateForAppointment(Appointment $appointment, User $actor): Encounter
    {
        $patientRecord = PatientRecord::where('patient_id', $appointment->patient_id)->first();

        if (! $patientRecord) {
            throw new NotFoundException('Medical record not found for this patient.');
        }

        return Encounter::firstOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'patient_record_id' => $patientRecord->id,
                'visit_type'        => $appointment->encounter_type?->value,
                'created_by'        => $actor->id,
            ]
        );
    }

    /**
     * دفاع مزدوج (نفس فلسفة Phase 7): تحقق ownership على الموعد، ثم
     * قيد in_progress الخاص بهذه الوحدة، ثم AccessGuard::canPerform()
     * كطبقة تحقق نهائية مستقلة.
     *
     * @return array{0: Doctor, 1: Patient}
     * @throws AuthorizationException|BusinessException|NotFoundException
     */
    private function ensureCanWrite(Appointment $appointment, User $doctorUser, AccessAction $action): array
    {
        $doctor = $this->ensureOwnership($appointment, $doctorUser);

        if ($appointment->status !== AppointmentStatus::InProgress) {
            throw new BusinessException(
                'Clinical documentation can only be added while the appointment is in progress.'
            );
        }

        $patient = $appointment->patient;

        if (! app(AccessGuard::class)->canPerform($doctor, $patient, $action)) {
            throw new AuthorizationException('You do not currently have write access to this patient\'s medical record.');
        }

        return [$doctor, $patient];
    }

    private function ensureOwnership(Appointment $appointment, User $doctorUser): Doctor
    {
        $doctor = $doctorUser->doctor;

        if (! $doctor) {
            throw new NotFoundException('Doctor profile not found for this account.');
        }

        if ($appointment->doctor_id !== $doctor->id) {
            throw new AuthorizationException('This appointment does not belong to you.');
        }

        return $doctor;
    }

    private function logWrite(Doctor $doctor, Appointment $appointment, AccessAction $action, string $entityType, int $entityId): void
    {
        $access = app(AccessGuard::class)->resolveActiveAccess($doctor, $appointment->patient);

        if ($access) {
            app(AccessGuard::class)->logAccess($access, $action, $entityType, $entityId);
        }
    }
}
