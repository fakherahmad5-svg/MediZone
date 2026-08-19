<?php

use App\Modules\Medical\Controllers\AllergyController;
use App\Modules\Medical\Controllers\AttachmentController;
use App\Modules\Medical\Controllers\ChronicConditionController;
use App\Modules\Medical\Controllers\DoctorMedicalRecordController;
use App\Modules\Medical\Controllers\FamilyHistoryController;
use App\Modules\Medical\Controllers\MedicalRecordController;
use App\Modules\Medical\Controllers\MedicationController;
use App\Modules\Medical\Controllers\SurgeryController;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth:sanctum', 'role:patient'])
    ->prefix('patient/medical-record')
    ->name('patient.medical-record.')
    ->group(function () {

        // ── Full Aggregate View ────────────────────────
        Route::get('/', [MedicalRecordController::class, 'me'])->name('index');

        // ── Allergies ────────────────────────────────────────────
        Route::prefix('allergies')->name('allergies.')->group(function () {
            Route::get('/', [AllergyController::class, 'index'])->name('index');
            Route::post('/', [AllergyController::class, 'store'])->name('store');
            Route::put('/{id}', [AllergyController::class, 'update'])->name('update');
            Route::delete('/{id}', [AllergyController::class, 'destroy'])->name('destroy');
        });

        // ── Chronic Conditions ───────────────────────────────────
        Route::prefix('chronic-conditions')->name('chronic-conditions.')->group(function () {
            Route::get('/', [ChronicConditionController::class, 'index'])->name('index');
            Route::post('/', [ChronicConditionController::class, 'store'])->name('store');
            Route::put('/{id}', [ChronicConditionController::class, 'update'])->name('update');
            Route::delete('/{id}', [ChronicConditionController::class, 'destroy'])->name('destroy');
        });

        // ── Surgeries ────────────────────────────────────────────
        Route::prefix('surgeries')->name('surgeries.')->group(function () {
            Route::get('/', [SurgeryController::class, 'index'])->name('index');
            Route::post('/', [SurgeryController::class, 'store'])->name('store');
            Route::put('/{id}', [SurgeryController::class, 'update'])->name('update');
            Route::delete('/{id}', [SurgeryController::class, 'destroy'])->name('destroy');
        });

        // ── Family History ───────────────────────────────────────
        Route::prefix('family-history')->name('family-history.')->group(function () {
            Route::get('/', [FamilyHistoryController::class, 'index'])->name('index');
            Route::post('/', [FamilyHistoryController::class, 'store'])->name('store');
            Route::put('/{id}', [FamilyHistoryController::class, 'update'])->name('update');
            Route::delete('/{id}', [FamilyHistoryController::class, 'destroy'])->name('destroy');
        });

        // ── Medications ──────────────────────────────────────────
        Route::prefix('medications')->name('medications.')->group(function () {
            Route::get('/drugs/search', [MedicationController::class, 'searchDrugs'])->name('drugs.search');

            Route::get('/', [MedicationController::class, 'index'])->name('index');
            Route::post('/', [MedicationController::class, 'store'])->name('store');
            Route::put('/{id}', [MedicationController::class, 'update'])->name('update');
            Route::post('/{id}/stop', [MedicationController::class, 'stop'])->name('stop');
            Route::delete('/{id}', [MedicationController::class, 'destroy'])->name('destroy');
        });

        // ── Attachments ──────────────────────────────────────────
        Route::prefix('attachments')->name('attachments.')->group(function () {
            Route::get('/', [AttachmentController::class, 'index'])->name('index');
            Route::post('/', [AttachmentController::class, 'store'])->name('store');
            Route::get('/{id}/download', [AttachmentController::class, 'download'])->name('download');
            Route::delete('/{id}', [AttachmentController::class, 'destroy'])->name('destroy');
        });
    });
Route::middleware(['auth:sanctum', 'role:doctor'])
    ->prefix('doctor/appointments')
    ->name('doctor.appointments.')
    ->group(function () {
        Route::get('/{appointmentId}/medical-record', [DoctorMedicalRecordController::class, 'show'])
            ->name('medical-record');
        Route::get('/{patientId}/profile', [DoctorMedicalRecordController::class, 'patientProfile'])
            ->name('medical-record');
    });
