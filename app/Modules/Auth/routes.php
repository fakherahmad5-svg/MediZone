<?php

use Illuminate\Support\Facades\Route;



Route::prefix('auth')->name('auth.')->group(function () {
    Route::get('/health', fn () => response()->json([
        'success' => true,
        'message' => 'Auth module is ready. Implementation coming in Phase 2.',
        'version' => 'v1',
    ]));
});
