<?php
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

Route::put("/v1/profile", [ProfileController::class,"updateProfile"])
    ->name("profile_controller.update_profile")
    ->middleware('jwtAuth');
Route::put("/v1/profile/password", [ProfileController::class,"changePassword"])
    ->name("profile_controller.change_password")
    ->middleware('jwtAuth');
