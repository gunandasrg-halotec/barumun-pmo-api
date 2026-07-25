<?php

use App\Http\Controllers\Api\HeavyEquipmentLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Heavy Equipment — Data mentah laporan harian
|--------------------------------------------------------------------------
*/

Route::middleware('jwtAuth')->group(function () {

    Route::get('/v1/heavy-equipment/logs', [HeavyEquipmentLogController::class, 'index'])
        ->name('heavy-equipment.log.index');

    Route::get('/v1/heavy-equipment/logs/{log}', [HeavyEquipmentLogController::class, 'show'])
        ->name('heavy-equipment.log.show');

    Route::get('/v1/heavy-equipment/logs/{log}/photos/{photo}', [HeavyEquipmentLogController::class, 'downloadPhoto'])
        ->name('heavy-equipment.photo.download');
});
