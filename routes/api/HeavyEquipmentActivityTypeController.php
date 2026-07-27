<?php

use App\Http\Controllers\Api\HeavyEquipmentActivityTypeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Heavy Equipment — Master jenis pekerjaan (setup Admin)
|--------------------------------------------------------------------------
*/

Route::middleware('jwtAuth')->group(function () {

    Route::get('/v1/heavy-equipment/activity-types', [HeavyEquipmentActivityTypeController::class, 'index'])
        ->name('heavy-equipment.activity-type.index');

    Route::post('/v1/heavy-equipment/activity-types', [HeavyEquipmentActivityTypeController::class, 'store'])
        ->name('heavy-equipment.activity-type.store');

    Route::patch('/v1/heavy-equipment/activity-types/{activity_type}', [HeavyEquipmentActivityTypeController::class, 'update'])
        ->name('heavy-equipment.activity-type.update');
});
