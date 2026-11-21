<?php

use App\Http\Controllers\AdvisoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/enterprises/{enterprise:public_id}/advisory', [AdvisoryController::class, 'index'])
        ->name('enterprises.advisory.index');

    Route::post('/enterprises/{enterprise:public_id}/advisory', [AdvisoryController::class, 'store'])
        ->name('enterprises.advisory.store');

    Route::post('/enterprises/{enterprise:public_id}/follow-ups', [AdvisoryController::class, 'storeFollowUp'])
        ->name('enterprises.follow-ups.store');

    Route::post('/follow-ups/{followUp}/complete', [AdvisoryController::class, 'complete'])
        ->name('follow-ups.complete');

    Route::put('/follow-ups/{followUp}', [AdvisoryController::class, 'updateFollowUp'])
        ->name('follow-ups.update');
    Route::delete('/follow-ups/{followUp}', [AdvisoryController::class, 'destroyFollowUp'])
        ->name('follow-ups.destroy');
    Route::post('/follow-ups/{followUp}/reopen', [AdvisoryController::class, 'reopenFollowUp'])
        ->name('follow-ups.reopen');

    Route::put('/advisory-activities/{activity}', [AdvisoryController::class, 'update'])
        ->name('advisory-activities.update');
    Route::delete('/advisory-activities/{activity}', [AdvisoryController::class, 'destroy'])
        ->name('advisory-activities.destroy');
});
