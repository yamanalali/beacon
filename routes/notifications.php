<?php

use App\Http\Controllers\Notifications\NotificationController;
use Illuminate\Support\Facades\Route;

// In-app notifications (rules §43). Every role reads only its own.
Route::middleware('auth')->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('/page', [NotificationController::class, 'page'])->name('page');
    Route::post('/{notification}/read', [NotificationController::class, 'markRead'])
        ->whereUuid('notification')->name('read');
    Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
    Route::delete('/{notification}', [NotificationController::class, 'destroy'])
        ->whereUuid('notification')->name('destroy');
});
