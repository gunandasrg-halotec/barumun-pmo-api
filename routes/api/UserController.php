<?php
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get("/v1/users", [UserController::class,"index"])
    ->name("user_controller.index")
    ->middleware('jwtAuth');
Route::post("/v1/users", [UserController::class,"store"])
    ->name("user_controller.store")
    ->middleware('jwtAuth');
Route::patch("/v1/user/{user}", [UserController::class,"update"])
    ->name("user_controller.update")
    ->middleware('jwtAuth');
Route::patch("/v1/user/{user}/toggle-active", [UserController::class,"setUserActivation"])
    ->name("user_controller.set_user_activation")
    ->middleware('jwtAuth');
Route::get("/v1/users/total-by-role", [UserController::class,"userTotalByRole"])
    ->name("user_controller.user_total_by_role")
    ->middleware('jwtAuth');
