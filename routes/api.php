<?php

use App\Modules\Auth\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {

    require base_path('app/Modules/Auth/routes.php');
    require base_path('app/Modules/Departments/routes.php');
    require base_path('app/Modules/Clinics/routes.php');
    require base_path('app/Modules/Doctors/routes.php');
    require base_path('app/Modules/Receptionists/routes.php');
    require base_path('app/Modules/Patient/routes.php');
    require base_path('app/Modules/Scheduling/routes.php');

    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/ping', fn () => response()->json([
            'success' => true,
            'message' => 'Authenticated. Server is running.',
        ]));

        Route::middleware('role:admin')
            ->prefix('admin')
            ->name('admin.')
            ->group(function () {
            });
    });
});

Route::get('/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');