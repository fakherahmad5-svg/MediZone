<?php

use App\Modules\Appointments\Controllers\DoctorAppointmentController;
use App\Modules\Appointments\Controllers\PatientAppointmentController;
use App\Modules\Appointments\Controllers\ReceptionistAppointmentController;
use App\Modules\Doctors\Controllers\DoctorStripeOnboardingController;
use App\Modules\Payments\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;



// ── Patient ────────────────────────────────────
Route::middleware('role:patient')
    ->prefix('patient/appointments')
    ->name('patient.appointments.')
    ->group(function () {
        Route::get('/', [PatientAppointmentController::class, 'index'])->name('index');
        Route::post('/', [PatientAppointmentController::class, 'store'])->name('store');
        Route::get('/{id}', [PatientAppointmentController::class, 'show'])->name('show');
        Route::post('/{id}/cancel', [PatientAppointmentController::class, 'cancel'])->name('cancel');
        Route::post('/{id}/reschedule', [PatientAppointmentController::class, 'reschedule'])->name('reschedule');
        Route::post('/{id}/checkout', [PatientAppointmentController::class, 'checkout'])->name('checkout');
    });

// ── Doctor ────────────────────────────────
Route::middleware('role:doctor')
    ->prefix('doctor/appointments')
    ->name('doctor.appointments.')
    ->group(function () {
        Route::get('/', [DoctorAppointmentController::class, 'index'])->name('index');
        Route::get('/{id}', [DoctorAppointmentController::class, 'show'])->name('show');
        Route::post('/{id}/start', [DoctorAppointmentController::class, 'start'])->name('start');
        Route::post('/{id}/complete', [DoctorAppointmentController::class, 'complete'])->name('complete');
        Route::post('/{id}/cancel', [DoctorAppointmentController::class, 'cancel'])->name('cancel');
        Route::post('/{id}/no-show', [DoctorAppointmentController::class, 'noShow'])->name('no-show');
    });

// ── Receptionist ────────────────────────────────
Route::middleware('role:receptionist')
    ->prefix('receptionist/appointments')
    ->name('receptionist.appointments.')
    ->group(function () {
        Route::post('/walk-in', [ReceptionistAppointmentController::class, 'walkIn'])->name('walk-in');

        Route::get('/', [ReceptionistAppointmentController::class, 'index'])->name('index');
        Route::post('/', [ReceptionistAppointmentController::class, 'store'])->name('store');
        Route::get('/{id}', [ReceptionistAppointmentController::class, 'show'])->name('show');
        Route::post('/{id}/check-in', [ReceptionistAppointmentController::class, 'checkIn'])->name('check-in');
        Route::post('/{id}/cancel', [ReceptionistAppointmentController::class, 'cancel'])->name('cancel');
        Route::post('/{id}/no-show', [ReceptionistAppointmentController::class, 'noShow'])->name('no-show');
        Route::post('/{id}/confirm-cash-payment', [ReceptionistAppointmentController::class, 'confirmCashPayment'])->name('confirm-cash-payment');
    });
