<?php

use App\Modules\Doctors\Controllers\DoctorProfileController;
use App\Modules\Doctors\Controllers\DoctorSearchController;
use App\Modules\Doctors\Controllers\DoctorVerificationController;
use Illuminate\Support\Facades\Route;

// ── Public  ──────────────────────────────────────
Route::prefix('doctors')->name('doctors.')->group(function () {
    Route::get('/', [DoctorSearchController::class, 'search'])->name('search');
    Route::get('/{id}', [DoctorSearchController::class, 'show'])->name('show');
});

// ── Doctor Self-Service ────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:doctor'])
    ->prefix('doctor/profile')
    ->name('doctor.profile.')
    ->group(function () {
        Route::get('/', [DoctorProfileController::class, 'me'])->name('me');
        Route::put('/', [DoctorProfileController::class, 'update'])->name('update');
        Route::post('/photo', [DoctorProfileController::class, 'uploadPhoto'])->name('photo');
        Route::post('/certificates', [DoctorProfileController::class, 'uploadCertificate'])->name('certificates');
        Route::post('/clinics/join', [DoctorProfileController::class, 'joinClinic'])->name('clinics.join');
        Route::post('/clinics/create', [DoctorProfileController::class, 'createClinic'])->name('clinics.create');
        Route::post('/departments/leave', [DoctorProfileController::class, 'leaveDepartment'])->name('departments.leave');
    });

// ── Admin ─────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin/doctors')
    ->name('admin.doctors.')
    ->group(function () {
        Route::get('/pending', [DoctorVerificationController::class, 'pending'])->name('pending');
        Route::get('/', [DoctorVerificationController::class, 'adminIndex'])->name('index');
        Route::post('/{id}/approve', [DoctorVerificationController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [DoctorVerificationController::class, 'reject'])->name('reject');
        Route::post('/{id}/suspend', [DoctorVerificationController::class, 'suspend'])->name('suspend');
        Route::post('/{id}/reactivate', [DoctorVerificationController::class, 'reactivate'])->name('reactivate');
    });
