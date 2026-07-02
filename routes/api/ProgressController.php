<?php

use App\Http\Controllers\Api\ProgressController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Progress Routes — Phase 5: Progress Workflow
|--------------------------------------------------------------------------
|
| Business rules enforced in ProgressService:
| - Project must have active approved baseline before creating progress
| - Only ITEM-type WBD nodes can receive progress
| - PM → AUTO_APPROVED; Admin Proyek → PENDING_PM_APPROVAL
| - Only PM can approve / reject pending entries
|
*/

Route::middleware('jwtAuth')->group(function () {

    // Project-scoped list & create
    Route::get('/v1/projects/{project}/progress-entries', [ProgressController::class, 'index'])
        ->name('progress.index');

    Route::post('/v1/projects/{project}/progress-entries', [ProgressController::class, 'store'])
        ->name('progress.store');

    // Single entry operations
    Route::get('/v1/progress-entries/{progressEntry}', [ProgressController::class, 'show'])
        ->name('progress.show');

    Route::post('/v1/progress-entries/{progressEntry}/approve', [ProgressController::class, 'approve'])
        ->name('progress.approve');

    Route::post('/v1/progress-entries/{progressEntry}/reject', [ProgressController::class, 'reject'])
        ->name('progress.reject');

    // Attachment download
    Route::get('/v1/progress-entries/{progressEntry}/attachment', [ProgressController::class, 'downloadAttachment'])
        ->name('progress.attachment');
});
