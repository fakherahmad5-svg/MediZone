<?php

use App\Modules\DoctorEngagement\Controllers\AdminReportController;
use App\Modules\DoctorEngagement\Controllers\PatientFavoriteController;
use App\Modules\DoctorEngagement\Controllers\PatientReportController;
use App\Modules\DoctorEngagement\Controllers\PatientReviewController;
use App\Modules\DoctorEngagement\Controllers\PublicDoctorReviewController;
use Illuminate\Support\Facades\Route;



// ── Public  ──────────────────────────────────────────────
Route::prefix('doctors/{doctorId}')->name('doctors.')->group(function () {
    Route::get('/reviews', [PublicDoctorReviewController::class, 'index'])->name('reviews.index');
    Route::get('/reviews/summary', [PublicDoctorReviewController::class, 'summary'])->name('reviews.summary');
});

// ── Patient — ─────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:patient'])
    ->prefix('patient')
    ->name('patient.')
    ->group(function () {

        // Favorites
        Route::get('/favorites', [PatientFavoriteController::class, 'index'])->name('favorites.index');
        Route::post('/doctors/{doctorId}/favorite/toggle', [PatientFavoriteController::class, 'toggle'])->name('favorites.toggle');

        // Reviews
        Route::get('/doctors/{doctorId}/review', [PatientReviewController::class, 'show'])->name('reviews.show');
        Route::post('/doctors/{doctorId}/review', [PatientReviewController::class, 'store'])->name('reviews.store');
        Route::delete('/doctors/{doctorId}/review', [PatientReviewController::class, 'destroy'])->name('reviews.destroy');

        // Reports
        Route::get('/reports', [PatientReportController::class, 'index'])->name('reports.index');
        Route::post('/doctors/{doctorId}/report', [PatientReportController::class, 'store'])->name('reports.store');
        Route::post('/reports/{reportId}/feedback', [PatientReportController::class, 'feedback'])->name('reports.feedback');
    });

// ── Admin ───────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin/reports')
    ->name('admin.reports.')
    ->group(function () {
        Route::get('/', [AdminReportController::class, 'index'])->name('index');
        Route::post('/{reportId}/review', [AdminReportController::class, 'markUnderReview'])->name('review');
        Route::post('/{reportId}/resolve', [AdminReportController::class, 'resolve'])->name('resolve');
    });
