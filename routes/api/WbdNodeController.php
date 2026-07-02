<?php

use App\Http\Controllers\Api\WbdNodeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WBD Node Routes — Phase 3: WBD & Baseline Versioning
|--------------------------------------------------------------------------
*/

Route::middleware('jwtAuth')->group(function () {

    // Version-scoped node list & create
    Route::get('/v1/wbd-versions/{wbdVersion}/nodes', [WbdNodeController::class, 'index'])
        ->name('wbd-node.index');

    Route::post('/v1/wbd-versions/{wbdVersion}/nodes', [WbdNodeController::class, 'store'])
        ->name('wbd-node.store');

    // Single node operations
    Route::patch('/v1/wbd-nodes/{wbdNode}', [WbdNodeController::class, 'update'])
        ->name('wbd-node.update');

    Route::delete('/v1/wbd-nodes/{wbdNode}', [WbdNodeController::class, 'destroy'])
        ->name('wbd-node.destroy');
});
