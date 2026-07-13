<?php

use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::get('/health', fn () => response()->json([
        'success' => true,
        'message' => 'Auth module is ready.',
        'version' => 'v1',
        'endpoints' => [
            'POST /api/v1/auth/register',
            'POST /api/v1/auth/login',
            'POST /api/v1/auth/logout',
            'GET /api/v1/auth/me',
            'POST /api/v1/auth/forgot-password',
            'POST /api/v1/auth/reset-password',
            'POST /api/v1/auth/change-password',
            'POST /api/v1/auth/email/verify-code',
            'POST /api/v1/auth/email/resend-code',
        ],
    ]));

    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');



    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::post('/change-password', [AuthController::class, 'changePassword'])->name('change-password');

        Route::post('/email/verify-code', [VerificationController::class, 'verifyCode'])
            ->middleware('throttle:10,1')
            ->name('email.verify-code');

        Route::post('/email/resend-code', [VerificationController::class, 'resendCode'])
            ->middleware('throttle:3,1')
            ->name('email.resend-code');

        Route::post('/complete-profile', [AuthController::class, 'completeProfile'])
            ->name('complete-profile');
    });

});
