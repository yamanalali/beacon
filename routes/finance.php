<?php

use App\Http\Controllers\FinancialReportController;
use App\Http\Controllers\ReceiptExtractionController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::post('/transactions/{transaction:public_id}/approve', [TransactionController::class, 'approve'])->name('transactions.approve');
    Route::get('/reports', [FinancialReportController::class, 'entry'])->name('reports.entry');
    Route::get('/enterprises/{enterprise:public_id}/reports', [FinancialReportController::class, 'show'])
        ->name('enterprises.reports.show');
    Route::get('/enterprises/{enterprise:public_id}/reports/export/{format}', [FinancialReportController::class, 'export'])
        ->middleware('throttle:30,1')
        ->name('enterprises.reports.export');

    Route::post('/documents/{document:public_id}/extractions', [ReceiptExtractionController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('documents.extractions.store');
    Route::get('/enterprises/{enterprise:public_id}/extractions/pending', [ReceiptExtractionController::class, 'pending'])
        ->name('enterprises.extractions.pending');
    Route::get('/extractions/{extraction:public_id}', [ReceiptExtractionController::class, 'show'])
        ->name('extractions.show');
    Route::post('/extractions/{extraction:public_id}/confirm', [ReceiptExtractionController::class, 'confirm'])
        ->name('extractions.confirm');
    Route::post('/extractions/{extraction:public_id}/reject', [ReceiptExtractionController::class, 'reject'])
        ->name('extractions.reject');
});
