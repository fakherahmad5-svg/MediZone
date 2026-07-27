<?php

use App\Modules\Receptionists\Controllers\ReceptionistVerificationController;
use Illuminate\Support\Facades\Route;

/**
 * app/Modules/Receptionists/routes.php
 *
 * يُستدعى من routes/api.php: require base_path('app/Modules/Receptionists/routes.php');
 * كل الـ routes هنا إدارية بحتة (لا يوجد self-service منفصل لموظف
 * الاستقبال في Phase 3 — سيُضاف عند بناء وحدة المواعيد في Phase 6/7
 * حيث يستخدم الموظف حسابه فعلياً لإدارة الجدولة).
 */
Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin/receptionists')
    ->name('admin.receptionists.')
    ->group(function () {
        Route::get('/pending', [ReceptionistVerificationController::class, 'pending'])->name('pending');
        Route::get('/', [ReceptionistVerificationController::class, 'adminIndex'])->name('index');
        Route::post('/{id}/approve', [ReceptionistVerificationController::class, 'approve'])->name('approve');
        Route::post('/{id}/reject', [ReceptionistVerificationController::class, 'reject'])->name('reject');
        Route::post('/{id}/suspend', [ReceptionistVerificationController::class, 'suspend'])->name('suspend');
        Route::post('/{id}/reactivate', [ReceptionistVerificationController::class, 'reactivate'])->name('reactivate');
    });
