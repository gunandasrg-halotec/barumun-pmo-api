<?php

use App\Http\Controllers\Api\HeavyEquipmentCostItemController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Heavy Equipment — Katalog item biaya (setup Finance)
|--------------------------------------------------------------------------
*/

Route::middleware('jwtAuth')->group(function () {

    Route::get('/v1/heavy-equipment/cost-items', [HeavyEquipmentCostItemController::class, 'index'])
        ->name('heavy-equipment.cost-item.index');

    Route::post('/v1/heavy-equipment/cost-items', [HeavyEquipmentCostItemController::class, 'store'])
        ->name('heavy-equipment.cost-item.store');

    Route::patch('/v1/heavy-equipment/cost-items/{costItem}', [HeavyEquipmentCostItemController::class, 'update'])
        ->name('heavy-equipment.cost-item.update');
});
