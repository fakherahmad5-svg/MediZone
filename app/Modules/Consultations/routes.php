<?php

use App\Modules\Consultations\Controllers\ConsultationController;
use Illuminate\Support\Facades\Route;

// Ownership (doctor or patient on the appointment) is enforced in
// ConsultationService::assertParticipant — no role middleware here since
// both sides of the same appointment use this identical route set.
Route::middleware('auth:sanctum')
    ->get('consultations', [ConsultationController::class, 'index'])
    ->name('consultations.index');

Route::middleware('auth:sanctum')
    ->prefix('appointments/{appointmentId}/consultation')
    ->name('consultations.')
    ->group(function () {
        Route::get('/', [ConsultationController::class, 'show'])->name('show');
        Route::get('/messages', [ConsultationController::class, 'messages'])->name('messages.index');
        Route::post('/messages', [ConsultationController::class, 'sendMessage'])->name('messages.store');
        Route::post('/messages/read', [ConsultationController::class, 'markRead'])->name('messages.read');
    });
