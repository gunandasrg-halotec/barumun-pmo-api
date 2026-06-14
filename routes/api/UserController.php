<?php
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get("/v1/users", [UserController::class,"index"])
    ->name("user_controller.index")
    ->middleware('jwtAuth');
Route::post("/v1/user", [UserController::class,"store"])
    ->name("user_controller.store")
    ->middleware('jwtAuth');
Route::patch("/v1/user/{user}", [UserController::class,"update"])
    ->name("user_controller.update")
    ->middleware('jwtAuth');
