<?php

use App\Http\Controllers\DocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/documents', [DocumentController::class, 'entry'])->name('documents.entry');

    Route::get('/enterprises/{enterprise:public_id}/documents', [DocumentController::class, 'index'])
        ->name('enterprises.documents.index');
    Route::post('/enterprises/{enterprise:public_id}/documents', [DocumentController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('enterprises.documents.store');

    Route::get('/documents/{document:public_id}/preview', [DocumentController::class, 'preview'])
        ->name('documents.preview');
    Route::get('/documents/{document:public_id}/download', [DocumentController::class, 'download'])
        ->name('documents.download');
});

Route::middleware('auth')->group(function () {
    Route::get('/documents/{document:public_id}', [DocumentController::class, 'show'])->name('documents.show');
    Route::put('/documents/{document:public_id}', [DocumentController::class, 'update'])->name('documents.update');
    Route::post('/documents/{document:public_id}/archive', [DocumentController::class, 'archive'])->name('documents.archive');
});
