<?php

use App\Modules\Access\Controllers\PatientAccessController;
use Illuminate\Support\Facades\Route;



Route::middleware('role:patient')
    ->prefix('patient/access')
    ->name('patient.access.')
    ->group(function () {
        Route::get('/', [PatientAccessController::class, 'index'])->name('index');
        Route::post('/appointments/{id}/revoke', [PatientAccessController::class, 'revokeAppointment'])->name('revoke-appointment');
        Route::post('/doctors/{id}/revoke', [PatientAccessController::class, 'revokeDoctor'])->name('revoke-doctor');
    });
