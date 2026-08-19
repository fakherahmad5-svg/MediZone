<?php

use App\Modules\Patient\Controllers\PatientController;
use App\Modules\Patient\Controllers\PatientProfileController;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth:sanctum', 'role:patient'])
    ->prefix('patient/profile')
    ->name('patient.profile.')
    ->group(function () {
        Route::get('/', [PatientProfileController::class, 'me'])->name('me');
        Route::put('/', [PatientProfileController::class, 'update'])->name('update');
    });
Route::middleware('role:receptionist')
    ->prefix('receptionist/patients')
    ->name('receptionist.patients.')
    ->group(function () {
        Route::get('/search', [PatientController::class, 'search'])->name('search');
    });
