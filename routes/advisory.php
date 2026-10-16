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
});
