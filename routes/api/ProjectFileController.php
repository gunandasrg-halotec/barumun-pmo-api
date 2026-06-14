<?php

use App\Http\Controllers\Api\ProjectFileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Project File Routes — Phase 7: File Repository
|--------------------------------------------------------------------------
|
| Business rules enforced in ProjectFileRequest:
| - IMAGE requires caption + photo_date
| - related_entity_type requires related_entity_id
| - Only PM and Admin Proyek can upload / archive files
|
*/

Route::middleware('jwtAuth')->group(function () {

    // Project-scoped list & upload
    Route::get('/v1/projects/{project}/files', [ProjectFileController::class, 'index'])
        ->name('project-file.index');

    Route::post('/v1/projects/{project}/files', [ProjectFileController::class, 'store'])
        ->name('project-file.store');

    // Single file operations
    Route::get('/v1/files/{projectFile}', [ProjectFileController::class, 'show'])
        ->name('project-file.show');

    // Archive (soft-delete) — uses DELETE verb
    Route::delete('/v1/files/{projectFile}', [ProjectFileController::class, 'destroy'])
        ->name('project-file.destroy');
});
