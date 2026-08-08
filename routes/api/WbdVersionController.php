<?php

use App\Http\Controllers\Api\WbdVersionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WBD Version Routes — Phase 3: WBD & Baseline Versioning
|--------------------------------------------------------------------------
*/

Route::middleware('jwtAuth')->group(function () {

    // Project-scoped WBD version list & create
    Route::get('/v1/projects/{project}/wbd-versions', [WbdVersionController::class, 'index'])
        ->name('wbd-version.index');

    Route::post('/v1/projects/{project}/wbd-versions', [WbdVersionController::class, 'store'])
        ->name('wbd-version.store');

    // Global: pending approvals for Direksi — MUST be before {wbdVersion} parameterized routes
    Route::get('/v1/wbd-versions/pending', [WbdVersionController::class, 'pending'])
        ->name('wbd-version.pending');

    // Single version operations
    Route::get('/v1/wbd-versions/{wbdVersion}', [WbdVersionController::class, 'show'])
        ->name('wbd-version.show');

    Route::post('/v1/wbd-versions/{wbdVersion}/submit', [WbdVersionController::class, 'submit'])
        ->name('wbd-version.submit');

    Route::post('/v1/wbd-versions/{wbdVersion}/approve', [WbdVersionController::class, 'approve'])
        ->name('wbd-version.approve');

    Route::post('/v1/wbd-versions/{wbdVersion}/reject', [WbdVersionController::class, 'reject'])
        ->name('wbd-version.reject');

    // Revisi baseline in-place
    Route::get('/v1/wbd-versions/{wbdVersion}/diff', [WbdVersionController::class, 'diff'])
        ->name('wbd-version.diff');

    Route::post('/v1/wbd-versions/{wbdVersion}/finalize', [WbdVersionController::class, 'finalize'])
        ->name('wbd-version.finalize');

    Route::post('/v1/wbd-versions/{wbdVersion}/unlock-revision', [WbdVersionController::class, 'unlockRevision'])
        ->name('wbd-version.unlock-revision');

    Route::post('/v1/wbd-versions/{wbdVersion}/revoke-unlock', [WbdVersionController::class, 'revokeUnlock'])
        ->name('wbd-version.revoke-unlock');

    Route::post('/v1/wbd-versions/{wbdVersion}/start-revision', [WbdVersionController::class, 'startRevision'])
        ->name('wbd-version.start-revision');
});
