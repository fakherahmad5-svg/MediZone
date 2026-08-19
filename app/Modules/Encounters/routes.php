<?php

use App\Modules\Encounters\Controllers\DoctorEncounterController;
use Illuminate\Support\Facades\Route;

/**
 * app/Modules/Encounters/routes.php
 * الحالة: [NEW - Phase 8]
 *
 * يُستدعى من routes/api.php بنفس أسلوب Access/Appointments:
 *   require base_path('app/Modules/Encounters/routes.php');
 *
 * يشارك نفس بادئة URL (doctor/appointments) مع Appointments/Medical،
 * لكن بأسماء routes مختلفة (doctor.appointments.encounter.*) — لا
 * تعارض، Laravel يسمح بمساهمة عدة ملفات في نفس البادئة.
 */

Route::middleware(['auth:sanctum', 'role:doctor'])
    ->prefix('doctor/appointments/{appointmentId}/encounter')
    ->name('doctor.appointments.encounter.')
    ->group(function () {
        Route::get('/', [DoctorEncounterController::class, 'show'])->name('show');
        Route::post('/notes', [DoctorEncounterController::class, 'storeNote'])->name('notes.store');
        Route::post('/diagnoses', [DoctorEncounterController::class, 'storeDiagnosis'])->name('diagnoses.store');
        Route::post('/prescription-items', [DoctorEncounterController::class, 'storePrescriptionItem'])->name('prescription-items.store');
        Route::post('/submit', [DoctorEncounterController::class, 'submit'])->name('submit');
    });
