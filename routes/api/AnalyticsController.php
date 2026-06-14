<?php

use App\Http\Controllers\Api\AnalyticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Analytics Routes — Phase 8: Dashboard and Analytics
|--------------------------------------------------------------------------
|
| SRS rule: ALL analytics use ONLY officially approved data:
|   - Progress: APPROVED + AUTO_APPROVED
|   - Cost:     APPROVED
|   - Baseline: active FINAL_APPROVED WBD version
|
| All routes are read-only (GET only).
|
*/

Route::middleware('jwtAuth')->group(function () {

    Route::get('/v1/projects/{project}/dashboard', [AnalyticsController::class, 'dashboard'])
        ->name('analytics.dashboard');

    Route::get('/v1/projects/{project}/s-curve', [AnalyticsController::class, 'sCurve'])
        ->name('analytics.s-curve');

    Route::get('/v1/projects/{project}/cost-analysis', [AnalyticsController::class, 'costAnalysis'])
        ->name('analytics.cost-analysis');
});
