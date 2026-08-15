<?php

use App\Modules\Auth\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ──────────────────────────────────────────────────────────────────
    // PUBLIC ROUTES
    // ──────────────────────────────────────────────────────────────────
    require base_path('app/Modules/Auth/routes.php');

    require base_path('app/Modules/Departments/routes.php');
    require base_path('app/Modules/Clinics/routes.php');
    require base_path('app/Modules/Doctors/routes.php');
    require base_path('app/Modules/Receptionists/routes.php');
    require base_path('app/Modules/Patient/routes.php');
    require base_path('app/Modules/Medical/routes.php');
    require base_path('app/Modules/Scheduling/routes.php');

    // ──────────────────────────────────────────────────────────────────
    // PROTECTED ROUTES
    // ──────────────────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {


        Route::get('/ping', fn () => response()->json([
            'success' => true,
            'message' => 'Authenticated. Server is running.',
        ]));

        require base_path('app/Modules/Appointments/routes.php');
        // ── Users & Profiles ─────────────────────────────────────────
        // require base_path('app/Modules/Users/routes.php');

        // ── Medical Records ──────────────────────────────────────────
        // require base_path('app/Modules/Medical/routes.php');

        // ── Access Control ───────────────────────────────────────────
        // require base_path('app/Modules/AccessControl/routes.php');

        // ── Clinics & Departments ────────────────────────────────────
        // require base_path('app/Modules/Clinics/routes.php');

        // ── Scheduling ───────────────────────────────────────────────
        // require base_path('app/Modules/Scheduling/routes.php');

        // ── Appointments ─────────────────────────────────────────────
        // require base_path('app/Modules/Appointments/routes.php');

        // ── Encounters ───────────────────────────────────────────────
        // require base_path('app/Modules/Encounters/routes.php');

        // ── Consultations ────────────────────────────────────────────
        // require base_path('app/Modules/Consultations/routes.php');

        // ── Payments ─────────────────────────────────────────────────
        // require base_path('app/Modules/Payments/routes.php');

        // ── Notifications ────────────────────────────────────────────
        // require base_path('app/Modules/Notifications/routes.php');

        // ── Reviews & Reports ────────────────────────────────────────
        // require base_path('app/Modules/Reviews/routes.php');

        // ── Admin Only ───────────────────────────────────────────────
        Route::middleware('role:admin')
            ->prefix('admin')
            ->name('admin.')
            ->group(function () {
                // require base_path('app/Modules/Administration/routes.php');
            });
    });

});
Route::get('/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');
