<?php

use App\Http\Controllers\Api\GanttController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Gantt Routes — Phase 4: Gantt Read-Only Module
|--------------------------------------------------------------------------
|
| SRS rule: Gantt is STRICTLY read-only.
| No POST / PATCH / DELETE routes exist for Gantt.
| All data is derived from the active approved WBD baseline.
|
*/

Route::middleware('jwtAuth')->group(function () {

    Route::get('/v1/projects/{project}/gantt', [GanttController::class, 'index'])
        ->name('gantt.index');
});
