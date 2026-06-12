<?php
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post("/v1/auth/login", [AuthController::class,"login"])
    ->name("auth_controller.login");
Route::get("/v1/auth/me", [AuthController::class,"me"])
    ->name("auth_controller.me")
    ->middleware('jwtAuth');
Route::post("/v1/auth/logout", [AuthController::class,"logout"])
    ->name("auth_controller.logout")
    ->middleware('jwtAuth');
