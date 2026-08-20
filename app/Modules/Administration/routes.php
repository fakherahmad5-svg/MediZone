<?php

use App\Modules\Administration\Controllers\AdminDoctorReviewController;
use App\Modules\Administration\Controllers\AnalyticsController;
use App\Modules\Administration\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;


Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::prefix('analytics')->name('analytics.')->group(function () {
            Route::get('/dashboard', [AnalyticsController::class, 'dashboard'])->name('dashboard');
            Route::get('/appointments', [AnalyticsController::class, 'appointmentStats'])->name('appointments');
            Route::get('/doctors/performance', [AnalyticsController::class, 'doctorPerformance'])->name('doctors.performance');
            Route::get('/users/engagement', [AnalyticsController::class, 'userEngagement'])->name('users.engagement');
            Route::get('/reports/summary', [AnalyticsController::class, 'reportsSummary'])->name('reports.summary');
        });

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

        Route::get('/doctors/{doctorId}/reviews', [AdminDoctorReviewController::class, 'index'])->name('doctors.reviews');
    });
