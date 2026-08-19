<?php

namespace App\Modules\Encounters\Controllers;

use App\Core\Exceptions\NotFoundException;
use App\Core\Http\Controllers\BaseController;
use App\Models\Appointment;
use App\Modules\Encounters\Requests\StoreClinicalNoteRequest;
use App\Modules\Encounters\Requests\StoreDiagnosisRequest;
use App\Modules\Encounters\Requests\StorePrescriptionItemRequest;
use App\Modules\Encounters\Requests\SubmitEncounterRequest;
use App\Modules\Encounters\Resources\ClinicalNoteResource;
use App\Modules\Encounters\Resources\DiagnosisResource;
use App\Modules\Encounters\Resources\EncounterResource;
use App\Modules\Encounters\Resources\PrescriptionResource;
use App\Modules\Encounters\Services\EncounterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DoctorEncounterController
 *
 * مكان الملف: app/Modules/Encounters/Controllers/DoctorEncounterController.php
 * الحالة: [NEW - Phase 8]
 *
 * نفس نمط IDOR من DoctorMedicalRecordController (Phase 7 verification):
 * كل شيء مرتبط بموعد (appointment_id)، لا patient_id حراً من الطبيب.
 *
 * Routes [auth:sanctum, role:doctor]، تحت doctor/appointments/{id}/encounter/*:
 *   GET  /                    → show()
 *   POST /notes                → storeNote()
 *   POST /diagnoses            → storeDiagnosis()
 *   POST /prescription-items   → storePrescriptionItem()
 */
class DoctorEncounterController extends BaseController
{
    public function __construct(
        private readonly EncounterService $encounters,
    ) {}

    public function show(Request $request, int $appointmentId): JsonResponse
    {
        $appointment = $this->resolveAppointment($appointmentId);

        $encounter = $this->encounters->forAppointment($appointment, $request->user());

        return $this->successResponse(
            $encounter ? new EncounterResource($encounter) : null,
            $encounter ? 'Encounter retrieved successfully.' : 'No encounter has been started for this appointment yet.'
        );
    }

    public function submit(SubmitEncounterRequest $request, int $appointmentId): JsonResponse
    {
        $appointment = $this->resolveAppointment($appointmentId);

        $encounter = $this->encounters->submit(
            $appointment,
            $request->user(),
            $request->input('notes', []),
            $request->input('diagnoses', []),
            $request->input('prescription_items', [])
        );

        return $this->createdResponse(new EncounterResource($encounter), 'Encounter submitted successfully.');
    }
    public function storeNote(StoreClinicalNoteRequest $request, int $appointmentId): JsonResponse
    {
        $appointment = $this->resolveAppointment($appointmentId);

        $note = $this->encounters->addClinicalNote(
            $appointment,
            $request->user(),
            $request->validated('content')
        );

        return $this->createdResponse(new ClinicalNoteResource($note), 'Clinical note added successfully.');
    }

    public function storeDiagnosis(StoreDiagnosisRequest $request, int $appointmentId): JsonResponse
    {
        $appointment = $this->resolveAppointment($appointmentId);

        $diagnosis = $this->encounters->addDiagnosis(
            $appointment,
            $request->user(),
            $request->validated('label'),
            $request->validated('description')
        );

        return $this->createdResponse(new DiagnosisResource($diagnosis), 'Diagnosis added successfully.');
    }

    public function storePrescriptionItem(StorePrescriptionItemRequest $request, int $appointmentId): JsonResponse
    {
        $appointment = $this->resolveAppointment($appointmentId);

        $item = $this->encounters->addPrescriptionItem(
            $appointment,
            $request->user(),
            $request->validated('drug_id'),
            $request->validated()
        );

        return $this->createdResponse(
            new PrescriptionResource($item->prescription()->with('items.drug')->first()),
            'Prescription item added successfully.'
        );
    }

    // ─────────────────────────────────────────────────────────────

    private function resolveAppointment(int $appointmentId): Appointment
    {
        $appointment = Appointment::with('patient')->find($appointmentId);

        if (! $appointment) {
            throw new NotFoundException('Appointment not found.');
        }

        return $appointment;
    }
}
