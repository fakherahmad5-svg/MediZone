<?php

use App\Modules\Clinics\Controllers\ClinicController;
use Illuminate\Support\Facades\Route;


Route::prefix('clinics')->name('clinics.')->group(function () {
    Route::get('/', [ClinicController::class, 'index'])->name('index');
    Route::get('/{id}', [ClinicController::class, 'show'])->name('show');
});

// ── Admin ────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin/clinics')
    ->name('admin.clinics.')
    ->group(function () {
        Route::get('/pending', [ClinicController::class, 'pending'])->name('pending');
        Route::get('/', [ClinicController::class, 'adminIndex'])->name('index');
        Route::post('/', [ClinicController::class, 'store'])->name('store');
        Route::put('/{id}', [ClinicController::class, 'update'])->name('update');
        Route::put('/{id}/departments', [ClinicController::class, 'syncDepartments'])->name('departments');
        Route::post('/{id}/approve', [ClinicController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [ClinicController::class, 'reject'])->name('reject');
        Route::post('/{id}/suspend', [ClinicController::class, 'suspend'])->name('suspend');
        Route::post('/{id}/reactivate', [ClinicController::class, 'reactivate'])->name('reactivate');
    });
