<?php

use Illuminate\Support\Facades\Route;

/**
 * routes/api.php
 *
 * هذا الملف هو نقطة دخول جميع الـ API Routes.
 *
 * المبدأ المعتمد:
 *   - هذا الملف فقط يُنظِّم الـ Groups والـ Middleware
 *   - كل Module له ملف routes.php خاص به
 *   - نستخدم require_once لتضمين routes كل Module
 *
 * URL النهائية: /api/v1/...
 * (الـ /api يأتي من bootstrap/app.php)
 * (الـ /v1 يأتي من الـ prefix هنا)
 */

Route::prefix('v1')->name('api.v1.')->group(function () {

    require_once __DIR__ . '/../app/Modules/Auth/routes.php';


    Route::middleware('auth:sanctum')->group(function () {


        Route::middleware('role:admin')
            ->prefix('admin')
            ->name('admin.')
            ->group(function () {

            });
    });
});
