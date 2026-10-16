<?php

use App\Http\Controllers\EnterpriseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/enterprises', [EnterpriseController::class, 'index'])->name('enterprises.index');
    Route::get('/enterprises/create', [EnterpriseController::class, 'create'])->name('enterprises.create');
    Route::post('/enterprises', [EnterpriseController::class, 'store'])->name('enterprises.store');
    Route::get('/enterprises/{enterprise:public_id}', [EnterpriseController::class, 'show'])->name('enterprises.show');
    Route::get('/enterprises/{enterprise:public_id}/edit', [EnterpriseController::class, 'edit'])->name('enterprises.edit');
    Route::put('/enterprises/{enterprise:public_id}', [EnterpriseController::class, 'update'])->name('enterprises.update');
    Route::post('/enterprises/{enterprise:public_id}/advisors', [EnterpriseController::class, 'storeAdvisor'])->name('enterprises.advisors.store');
    Route::post('/enterprises/{enterprise:public_id}/members', [EnterpriseController::class, 'storeMember'])->name('enterprises.members.store');
});
