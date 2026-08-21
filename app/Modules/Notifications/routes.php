<?php

use App\Modules\Notifications\Controllers\DeviceTokenController;
use App\Modules\Notifications\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;


Route::middleware('auth:sanctum')
    ->prefix('notifications')
    ->name('notifications.')
    ->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('mark-read');
    });

Route::middleware('auth:sanctum')
    ->prefix('device-tokens')
    ->name('device-tokens.')
    ->group(function () {
        Route::post('/', [DeviceTokenController::class, 'store'])->name('store');
        Route::delete('/{token}', [DeviceTokenController::class, 'destroy'])->name('destroy');
    });
