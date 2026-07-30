<?php

use App\Http\Controllers\Api\FuelStockController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Heavy Equipment — Stock BBM (authenticated)
|--------------------------------------------------------------------------
*/

Route::middleware('jwtAuth')->group(function () {

    Route::get('/v1/heavy-equipment/fuel-stock', [FuelStockController::class, 'index'])
        ->name('heavy-equipment.fuel-stock.index');

    Route::get('/v1/heavy-equipment/fuel-stock/photos/{photo}', [FuelStockController::class, 'downloadPhoto'])
        ->name('fuel-stock.photo.download');
});
