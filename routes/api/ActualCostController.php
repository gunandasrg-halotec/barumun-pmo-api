<?php

use App\Http\Controllers\Api\ActualCostController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Actual Cost Routes — Phase 6: Actual Cost Workflow
|--------------------------------------------------------------------------
|
| Business rules enforced in CostService:
| - progress_entry_id is mandatory (no cost without linked progress)
| - PM cannot input cost (canInputCost = Finance | Admin Proyek)
| - All new entries go to REVIEW status
| - Finance only can approve / reject
|
*/

Route::middleware('jwtAuth')->group(function () {

    // Project-scoped list & create
    Route::get('/v1/projects/{project}/actual-cost-transactions', [ActualCostController::class, 'index'])
        ->name('actual-cost.index');

    Route::post('/v1/projects/{project}/actual-cost-transactions', [ActualCostController::class, 'store'])
        ->name('actual-cost.store');

    // Single transaction operations
    Route::get('/v1/actual-cost-transactions/{actualCostTransaction}', [ActualCostController::class, 'show'])
        ->name('actual-cost.show');

    Route::post('/v1/actual-cost-transactions/{actualCostTransaction}/approve', [ActualCostController::class, 'approve'])
        ->name('actual-cost.approve');

    Route::post('/v1/actual-cost-transactions/{actualCostTransaction}/reject', [ActualCostController::class, 'reject'])
        ->name('actual-cost.reject');
});
