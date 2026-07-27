<?php

use App\Modules\Patient\Controllers\PatientProfileController;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth:sanctum', 'role:patient'])
    ->prefix('patient/profile')
    ->name('patient.profile.')
    ->group(function () {
        Route::get('/', [PatientProfileController::class, 'me'])->name('me');
        Route::put('/', [PatientProfileController::class, 'update'])->name('update');
    });
