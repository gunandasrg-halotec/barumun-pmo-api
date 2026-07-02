<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwtAuth')->group(function () {
    Route::get('/v1/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');

    Route::post('/v1/notifications/read-all', [NotificationController::class, 'markAllRead'])
        ->name('notifications.read-all');

    Route::post('/v1/notifications/{notification}/read', [NotificationController::class, 'markRead'])
        ->name('notifications.read');
});
