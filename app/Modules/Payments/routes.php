<?php

use App\Modules\Payments\Controllers\AdminWalletController;
use App\Modules\Payments\Controllers\DoctorWalletController;
use App\Modules\Payments\Controllers\PatientAppointmentPaymentController;
use App\Modules\Payments\Controllers\PatientWalletController;
use Illuminate\Support\Facades\Route;

// ── Patient ──────────────────────────────────────
Route::middleware('role:patient')
    ->prefix('patient/wallet')
    ->name('patient.wallet.')
    ->group(function () {
        Route::get('/', [PatientWalletController::class, 'show'])->name('show');
        Route::get('/transactions', [PatientWalletController::class, 'transactions'])->name('transactions');
        Route::get('/payment-options', [PatientWalletController::class, 'paymentOptions'])->name('payment-options');
        Route::post('/top-up-requests', [PatientWalletController::class, 'storeTopUpRequest'])->name('top-up-requests.store');
        Route::get('/top-up-requests', [PatientWalletController::class, 'topUpRequests'])->name('top-up-requests.index');
    });

Route::middleware('role:patient')
    ->prefix('patient/appointments/{appointmentId}')
    ->name('patient.appointments.payment.')
    ->group(function () {
        Route::get('/payment-options', [PatientAppointmentPaymentController::class, 'options'])->name('options');
        Route::post('/pay', [PatientAppointmentPaymentController::class, 'pay'])->name('pay');
    });

// ── Doctor ────────────────────────────────────────
Route::middleware('role:doctor')
    ->prefix('doctor/wallet')
    ->name('doctor.wallet.')
    ->group(function () {
        Route::get('/', [DoctorWalletController::class, 'show'])->name('show');
        Route::get('/transactions', [DoctorWalletController::class, 'transactions'])->name('transactions');
        Route::post('/withdrawal-requests', [DoctorWalletController::class, 'storeWithdrawalRequest'])->name('withdrawal-requests.store');
        Route::get('/withdrawal-requests', [DoctorWalletController::class, 'withdrawalRequests'])->name('withdrawal-requests.index');
    });

// ── Admin ─────────────────────────────────────────
Route::middleware('role:admin')
    ->prefix('admin/wallet')
    ->name('admin.wallet.')
    ->group(function () {
        Route::get('/summary', [AdminWalletController::class, 'summary'])->name('summary');
        Route::get('/platform', [AdminWalletController::class, 'platform'])->name('platform');
        Route::get('/platform/transactions', [AdminWalletController::class, 'platformTransactions'])->name('platform.transactions');

        Route::get('/top-up-requests', [AdminWalletController::class, 'topUpRequests'])->name('top-up-requests.index');
        Route::post('/top-up-requests/{id}/approve', [AdminWalletController::class, 'approveTopUp'])->name('top-up-requests.approve');
        Route::post('/top-up-requests/{id}/reject', [AdminWalletController::class, 'rejectTopUp'])->name('top-up-requests.reject');

        Route::get('/withdrawal-requests', [AdminWalletController::class, 'withdrawalRequests'])->name('withdrawal-requests.index');
        Route::post('/withdrawal-requests/{id}/approve', [AdminWalletController::class, 'approveWithdrawal'])->name('withdrawal-requests.approve');
        Route::post('/withdrawal-requests/{id}/reject', [AdminWalletController::class, 'rejectWithdrawal'])->name('withdrawal-requests.reject');
    });
