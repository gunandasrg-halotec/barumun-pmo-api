<?php

use App\Http\Controllers\Api\FileCategoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| File Category Routes — Phase 7: File Repository
|--------------------------------------------------------------------------
*/

Route::middleware('jwtAuth')->group(function () {

    Route::get('/v1/file-categories', [FileCategoryController::class, 'index'])
        ->name('file-category.index');

    Route::post('/v1/file-categories', [FileCategoryController::class, 'store'])
        ->name('file-category.store');

    Route::patch('/v1/file-categories/{fileCategory}', [FileCategoryController::class, 'update'])
        ->name('file-category.update');
});
