<?php

use App\Http\Controllers\Api\WbdNodeDependencyController;

Route::post('/v1/wbd-nodes/{node}/dependencies', [WbdNodeDependencyController::class, 'store']);
Route::delete('/v1/wbd-node-dependencies/{dependency}', [WbdNodeDependencyController::class, 'destroy']);
