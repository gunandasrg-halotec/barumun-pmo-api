<?php

use App\Http\Controllers\Api\ResetSubmissionsController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/projects/{project}/reset-submissions', ResetSubmissionsController::class);
