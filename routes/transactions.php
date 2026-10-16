<?php

use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/transactions', [TransactionController::class, 'entry'])->name('transactions.entry');

    Route::get('/enterprises/{enterprise:public_id}/transactions', [TransactionController::class, 'index'])
        ->name('enterprises.transactions.index');
    Route::get('/enterprises/{enterprise:public_id}/transactions/create', [TransactionController::class, 'create'])
        ->name('enterprises.transactions.create');
    Route::post('/enterprises/{enterprise:public_id}/transactions', [TransactionController::class, 'store'])
        ->name('enterprises.transactions.store');

    Route::get('/transactions/{transaction:public_id}', [TransactionController::class, 'show'])
        ->name('transactions.show');
    Route::get('/transactions/{transaction:public_id}/correct', [TransactionController::class, 'editCorrection'])
        ->name('transactions.correct.edit');
    Route::post('/transactions/{transaction:public_id}/correct', [TransactionController::class, 'correct'])
        ->name('transactions.correct');
    Route::post('/transactions/{transaction:public_id}/settlements', [TransactionController::class, 'storeSettlement'])
        ->name('transactions.settlements.store');
});
