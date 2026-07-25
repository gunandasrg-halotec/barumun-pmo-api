<?php

use App\Http\Controllers\Api\PublicHeavyEquipmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Heavy Equipment — Endpoint PUBLIK (lapangan, tanpa login, proteksi PIN)
|--------------------------------------------------------------------------
*/

Route::middleware('pinAuth')->group(function () {

    Route::get('/v1/public/heavy-equipment/verify-pin', [PublicHeavyEquipmentController::class, 'verifyPin'])
        ->name('public.heavy-equipment.verify-pin');

    Route::get('/v1/public/heavy-equipment/equipments', [PublicHeavyEquipmentController::class, 'equipments'])
        ->name('public.heavy-equipment.equipments');

    Route::get('/v1/public/heavy-equipment/cost-items', [PublicHeavyEquipmentController::class, 'costItems'])
        ->name('public.heavy-equipment.cost-items');

    Route::get('/v1/public/heavy-equipment/activity-types', [PublicHeavyEquipmentController::class, 'activityTypes'])
        ->name('public.heavy-equipment.activity-types');

    Route::post('/v1/public/heavy-equipment/logs', [PublicHeavyEquipmentController::class, 'storeLog'])
        ->name('public.heavy-equipment.store-log');
});
