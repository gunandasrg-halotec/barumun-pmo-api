<?php

use App\Http\Controllers\Api\HeavyEquipmentAnalyticsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Heavy Equipment — Analitik penggunaan
|--------------------------------------------------------------------------
*/

Route::middleware('jwtAuth')->group(function () {

    Route::get('/v1/heavy-equipment/analytics', [HeavyEquipmentAnalyticsController::class, 'index'])
        ->name('heavy-equipment.analytics');
});
