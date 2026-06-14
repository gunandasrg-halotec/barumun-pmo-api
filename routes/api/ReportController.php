<?php

use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Report Routes — Phase 9: Reporting
|--------------------------------------------------------------------------
|
| Report generation allowed for: PM and Admin Proyek.
| Report viewing allowed for: all authenticated users.
|
*/

Route::middleware('jwtAuth')->group(function () {

    // Project-scoped report history
    Route::get('/v1/projects/{project}/reports', [ReportController::class, 'index'])
        ->name('report.index');

    // Generate a new report
    Route::post('/v1/projects/{project}/reports/generate', [ReportController::class, 'generate'])
        ->name('report.generate');

    // Single report record detail
    Route::get('/v1/reports/{reportRecord}', [ReportController::class, 'show'])
        ->name('report.show');
});
