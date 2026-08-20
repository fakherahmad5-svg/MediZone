<?php

use App\Modules\Payments\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

/**
 * Payments module routes.
 *
 * Checkout and Stripe Connect onboarding are intentionally NOT routed
 * from here. The official flows live in their owning modules:
 *
 *   - Checkout:   App\Modules\Appointments\Controllers\PatientAppointmentController::checkout()
 *                 registered in app/Modules/Appointments/routes.php
 *
 *   - Onboarding: App\Modules\Doctors\Controllers\DoctorStripeOnboardingController
 *                 registered in app/Modules/Doctors/routes.php
 *
 * The Stripe webhook is registered directly in routes/api.php, above
 * the auth:sanctum group, since it must remain unauthenticated.
 *
 * This file owns the read-only Payment viewing endpoints. No role
 * middleware is applied here — PaymentPolicy is the single source of
 * truth for who can see what, and PaymentQueryService scopes the data
 * per role (patient/doctor/clinic staff) internally.
 */

Route::prefix('payments')
    ->name('payments.')
    ->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::get('/{id}', [PaymentController::class, 'show'])->name('show');
    });