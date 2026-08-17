<?php

use App\Modules\Access\Controllers\PatientAccessController;
use Illuminate\Support\Facades\Route;

/**
 * app/Modules/Access/routes.php
 * الحالة: [NEW - Phase 7]
 *
 * يُستدعى من routes/api.php بنفس أسلوب استدعاء وحدة Appointments —
 * أضف السطر التالي هناك (داخل مجموعة auth:sanctum المركزية):
 *   require base_path('app/Modules/Access/routes.php');
 */

Route::middleware('role:patient')
    ->prefix('patient/access')
    ->name('patient.access.')
    ->group(function () {
        Route::get('/', [PatientAccessController::class, 'index'])->name('index');
        Route::post('/appointments/{id}/revoke', [PatientAccessController::class, 'revokeAppointment'])->name('revoke-appointment');
        Route::post('/doctors/{id}/revoke', [PatientAccessController::class, 'revokeDoctor'])->name('revoke-doctor');
    });
