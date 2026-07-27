<?php

use App\Modules\Departments\Controllers\DepartmentController;
use Illuminate\Support\Facades\Route;

/**
 * app/Modules/Departments/routes.php
 *
 * يُستدعى من routes/api.php (Phase 0 pattern):
 *   require base_path('app/Modules/Departments/routes.php');
 *
 * القسم العام (public) خارج auth:sanctum group لأنه يُستخدَم في
 * نماذج التسجيل قبل تسجيل الدخول (Doctor RegisterRequest/CompleteProfileRequest
 * تحتاج قائمة الأقسام لعرضها كخيارات).
 */

// ── Public ──────────────────────────────────────────────────────
Route::prefix('departments')->name('departments.')->group(function () {
    Route::get('/', [DepartmentController::class, 'index'])->name('index');
    Route::get('/{id}', [DepartmentController::class, 'show'])->name('show');
});

// ── Admin ────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin/departments')
    ->name('admin.departments.')
    ->group(function () {
        Route::get('/', [DepartmentController::class, 'adminIndex'])->name('index');
        Route::post('/', [DepartmentController::class, 'store'])->name('store');
        Route::put('/{id}', [DepartmentController::class, 'update'])->name('update');
        Route::delete('/{id}', [DepartmentController::class, 'destroy'])->name('destroy');
    });
