<?php

use App\Http\Controllers\Api\HeavyEquipmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Heavy Equipment — Master alat berat
|--------------------------------------------------------------------------
*/

Route::middleware('jwtAuth')->group(function () {

    Route::get('/v1/heavy-equipment', [HeavyEquipmentController::class, 'index'])
        ->name('heavy-equipment.index');

    Route::post('/v1/heavy-equipment', [HeavyEquipmentController::class, 'store'])
        ->name('heavy-equipment.store');

    Route::patch('/v1/heavy-equipment/{heavyEquipment}', [HeavyEquipmentController::class, 'update'])
        ->name('heavy-equipment.update');
});
