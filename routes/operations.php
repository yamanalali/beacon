<?php

use App\Http\Controllers\Operations\OperationsController;
use App\Http\Controllers\Operations\SessionController;
use Illuminate\Support\Facades\Route;

// Beacon sections 53, 54, 56. Authorization is enforced inside each controller action.
Route::middleware('auth')->group(function () {
    Route::get('/operations/security', [OperationsController::class, 'security'])->name('operations.security');
    Route::get('/operations/backups', [OperationsController::class, 'backups'])->name('operations.backups');
    Route::post('/operations/backups', [OperationsController::class, 'runBackup'])->name('operations.backups.run')->middleware('throttle:6,1');
    Route::post('/operations/backups/{backupRun}/restore-test', [OperationsController::class, 'restoreTest'])->name('operations.backups.restore-test')->middleware('throttle:6,1');
    Route::get('/operations/data-control', [OperationsController::class, 'dataControl'])->name('operations.data-control');

    Route::get('/account/sessions', [SessionController::class, 'index'])->name('account.sessions');
    Route::delete('/account/sessions/others', [SessionController::class, 'destroyOthers'])->name('account.sessions.destroy-others');
});
