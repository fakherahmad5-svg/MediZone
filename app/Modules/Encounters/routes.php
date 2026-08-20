<?php

use App\Modules\Encounters\Controllers\DoctorEncounterController;
use Illuminate\Support\Facades\Route;



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
