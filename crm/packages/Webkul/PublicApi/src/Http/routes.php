<?php

use Illuminate\Support\Facades\Route;
use Webkul\PublicApi\Http\Controllers\ActivityController;
use Webkul\PublicApi\Http\Controllers\AuthController;
use Webkul\PublicApi\Http\Controllers\ContactController;
use Webkul\PublicApi\Http\Controllers\LeadController;
use Webkul\PublicApi\Http\Controllers\PipelineController;
use Webkul\PublicApi\Http\Controllers\TagController;

Route::prefix('api/v1')->group(function () {
    // Public auth routes
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('api.v1.auth.login');

    // Protected routes
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');

        // Contacts (Persons)
        Route::apiResource('contacts', ContactController::class);

        // Leads
        Route::apiResource('leads', LeadController::class);

        // Activities
        Route::apiResource('activities', ActivityController::class);

        // Pipelines (read-only)
        Route::get('pipelines', [PipelineController::class, 'index'])->name('api.v1.pipelines.index');
        Route::get('pipelines/{id}', [PipelineController::class, 'show'])->name('api.v1.pipelines.show');

        // Tags
        Route::apiResource('tags', TagController::class)->except(['show']);
    });
});
