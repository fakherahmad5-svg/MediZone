<?php

use App\Modules\Scheduling\Controllers\AvailabilityController;
use App\Modules\Scheduling\Controllers\BlockedTimeController;
use App\Modules\Scheduling\Controllers\ReceptionistScheduleController;
use App\Modules\Scheduling\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;


// ── Public ──────────────────────────────────────
Route::get('/doctors/{doctorId}/availability', [AvailabilityController::class, 'index'])
    ->name('doctors.availability');

// ── Doctor Self-Service —
Route::middleware(['auth:sanctum', 'role:doctor'])
    ->prefix('doctor/schedule')
    ->name('doctor.schedule.')
    ->group(function () {
        Route::get('/', [ScheduleController::class, 'myClinics'])->name('index');

        Route::delete('/blocked-times/{id}', [BlockedTimeController::class, 'destroy'])
            ->name('blocked-times.destroy');

        Route::get('/{clinicId}', [ScheduleController::class, 'show'])->name('show');
        Route::put('/{clinicId}', [ScheduleController::class, 'setWeekly'])->name('update');
        Route::post('/{clinicId}/vacation', [ScheduleController::class, 'activateVacation'])->name('vacation.activate');
        Route::delete('/{clinicId}/vacation', [ScheduleController::class, 'deactivateVacation'])->name('vacation.deactivate');
        Route::post('/{clinicId}/generate-slots', [ScheduleController::class, 'generateSlots'])->name('generate-slots');

        Route::get('/{clinicId}/blocked-times', [BlockedTimeController::class, 'index'])->name('blocked-times.index');
        Route::post('/{clinicId}/blocked-times', [BlockedTimeController::class, 'store'])->name('blocked-times.store');
    });

// ── Receptionist On-Behalf —
Route::middleware(['auth:sanctum', 'role:receptionist'])
    ->prefix('receptionist')
    ->name('receptionist.')
    ->group(function () {

        Route::delete('/schedule/blocked-times/{id}', [ReceptionistScheduleController::class, 'destroyBlockedTime'])
            ->name('schedule.blocked-times.destroy');

        Route::prefix('doctors/{doctorId}/schedule')->name('doctors.schedule.')->group(function () {
            Route::get('/', [ReceptionistScheduleController::class, 'show'])->name('show');
            Route::put('/', [ReceptionistScheduleController::class, 'setWeekly'])->name('update');
            Route::post('/generate-slots', [ReceptionistScheduleController::class, 'generateSlots'])->name('generate-slots');
            Route::get('/blocked-times', [ReceptionistScheduleController::class, 'listBlockedTimes'])->name('blocked-times.index');
            Route::post('/blocked-times', [ReceptionistScheduleController::class, 'storeBlockedTime'])->name('blocked-times.store');
        });
    });
