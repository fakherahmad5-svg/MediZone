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
use App\Models\Drug;
use App\Models\Encounter;
use App\Models\Medication;
use App\Models\Patient;
use App\Models\PatientRecord;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\User;

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


    public function addPrescriptionItem(Appointment $appointment, User $doctorUser, string $drug_name,string $form, array $data): PrescriptionItem
    {
        [$doctor] = $this->ensureCanWrite($appointment, $doctorUser, AccessAction::CreatePrescription);

        return $this->transaction(function () use ($appointment, $doctor, $doctorUser, $drug_name,$form, $data) {
            $encounter = $this->resolveOrCreateForAppointment($appointment, $doctorUser);

            $prescription = Prescription::firstOrCreate(
                ['encounter_id' => $encounter->id],
                ['doctor_id' => $doctor->id]
            );
            $drug = Drug::firstOrCreate(
                ['name' => $drug_name],
                ['form' => $form ?? null, 'strength' => $data['strength'] ?? null]
            );

            $item = PrescriptionItem::create([
                'prescription_id' => $prescription->id,
                'drug_id'         => $drug->id,
                'dosage'          => $data['dosage'] ?? null,
                'frequency'       => $data['frequency'] ?? null,
                'duration'        => $data['duration'] ?? null,
                'route'           => $data['route'] ?? null,
                'notes'           => $data['notes'] ?? null,
            ]);


            Medication::create([
                'patient_record_id'    => $encounter->patient_record_id,
                'drug_id'              => $drug->id,
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

                $this->logWrite($doctor, $appointment, AccessAction::CreateNote, Diagnosis::class, $diagnosis->id);
            }

            if (! empty($prescriptionItems)) {
                $prescription = Prescription::firstOrCreate(
                    ['encounter_id' => $encounter->id],
                    ['doctor_id' => $doctor->id]
                );

                foreach ($prescriptionItems as $itemData) {

                    $drug = Drug::firstOrCreate(
                        ['name' => $itemData['drug_name']],
                        ['form' => $itemData['form'] ?? null, 'strength' => $itemData['strength'] ?? null]
                    );

                    $item = PrescriptionItem::create([
                        'prescription_id' => $prescription->id,
                        'drug_id'         => $drug->id,
                        'dosage'          => $itemData['dosage'] ?? null,
                        'frequency'       => $itemData['frequency'] ?? null,
                        'duration'        => $itemData['duration'] ?? null,
                        'route'           => $itemData['route'] ?? null,
                        'notes'           => $itemData['notes'] ?? null,
                    ]);

                    Medication::create([
                        'patient_record_id'    => $encounter->patient_record_id,
                        'drug_id'              => $drug->id,
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


    private function ensureCanWrite(Appointment $appointment, User $doctorUser, AccessAction $action): array
    {
        $doctor = $this->ensureOwnership($appointment, $doctorUser);

        if ($appointment->status !== AppointmentStatus::InProgress) {
            throw new BusinessException(
                'Clinical documentation can only be added while the appointment is in progress.'
            );
        }

        $patient = $appointment->patient;

        if (! app(AccessGuard::class)->canPerform($doctor, $patient, $action,$appointment)) {
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
        $access = app(AccessGuard::class)->resolveActiveAccess($doctor, $appointment->patient,$appointment);

        if ($access) {
            app(AccessGuard::class)->logAccess($access, $action, $entityType, $entityId);
        }
    }
}
