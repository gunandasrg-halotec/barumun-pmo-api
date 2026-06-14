<?php

use App\Http\Controllers\Api\ProjectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Project Routes  — Phase 2: Project Core
|--------------------------------------------------------------------------
|
| All routes protected by jwtAuth middleware.
| Access rule: all authenticated users can read; write restricted by role
| (enforced inside ProjectRequest::authorize()).
|
*/

Route::middleware('jwtAuth')->group(function () {

    // List all projects (search / filter / sort / paginate)
    Route::get('/v1/projects', [ProjectController::class, 'index'])
        ->name('project.index');

    // Create a new project
    Route::post('/v1/projects', [ProjectController::class, 'store'])
        ->name('project.store');

    // Get project detail
    Route::get('/v1/projects/{project}', [ProjectController::class, 'show'])
        ->name('project.show');

    // Update project (partial update)
    Route::patch('/v1/projects/{project}', [ProjectController::class, 'update'])
        ->name('project.update');
});
