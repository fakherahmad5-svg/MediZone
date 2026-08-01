<?php

use App\Modules\Scheduling\Controllers\ScheduleConfigController;
use App\Modules\Scheduling\Controllers\ScheduleDayController;
use App\Modules\Scheduling\Controllers\BlockedTimeController;
use App\Modules\Scheduling\Controllers\AvailabilityController;
use Illuminate\Support\Facades\Route;

Route::get('doctors/{doctor}/availability', [AvailabilityController::class, 'index'])->name('doctors.availability');

Route::middleware(['auth:sanctum', 'verified', 'role:doctor'])
    ->prefix('schedule')
    ->name('schedule.')
    ->group(function () {
        Route::post('config', [ScheduleConfigController::class, 'store'])->name('config.store');
        Route::get('config', [ScheduleConfigController::class, 'index'])->name('config.index');
        Route::put('config/{config}', [ScheduleConfigController::class, 'update'])->name('config.update');
        Route::patch('config/{config}/vacation', [ScheduleConfigController::class, 'toggleVacation'])->name('config.vacation');
        Route::post('config/{config}/days', [ScheduleDayController::class, 'store'])->name('days.store');
        Route::get('config/{config}/days', [ScheduleDayController::class, 'index'])->name('days.index');

        Route::post('blocked-times', [BlockedTimeController::class, 'store'])->name('blocked.store');
        Route::get('blocked-times', [BlockedTimeController::class, 'index'])->name('blocked.index');
        Route::delete('blocked-times/{blockedTime}', [BlockedTimeController::class, 'destroy'])->name('blocked.destroy');

        Route::post('config/{config}/generate-slots', [ScheduleConfigController::class, 'generateSlots'])->name('config.generate-slots');
    });