<?php

use App\Http\Controllers\EnterpriseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/enterprises', [EnterpriseController::class, 'index'])->name('enterprises.index');
    Route::get('/enterprises/export/{format}', [EnterpriseController::class, 'export'])
        ->whereIn('format', ['csv', 'xlsx'])
        ->name('enterprises.export');
    Route::get('/enterprises/create', [EnterpriseController::class, 'create'])->name('enterprises.create');
    Route::post('/enterprises', [EnterpriseController::class, 'store'])->name('enterprises.store');
    Route::get('/enterprises/{enterprise:public_id}', [EnterpriseController::class, 'show'])->name('enterprises.show');
    Route::get('/enterprises/{enterprise:public_id}/edit', [EnterpriseController::class, 'edit'])->name('enterprises.edit');
    Route::put('/enterprises/{enterprise:public_id}', [EnterpriseController::class, 'update'])->name('enterprises.update');
    Route::post('/enterprises/{enterprise:public_id}/archive', [EnterpriseController::class, 'archive'])->name('enterprises.archive');
    Route::post('/enterprises/{enterprise:public_id}/restore', [EnterpriseController::class, 'restore'])->name('enterprises.restore');
    Route::post('/enterprises/{enterprise:public_id}/advisors', [EnterpriseController::class, 'storeAdvisor'])->name('enterprises.advisors.store');
    Route::delete('/enterprises/{enterprise:public_id}/advisors/{user}', [EnterpriseController::class, 'destroyAdvisor'])->name('enterprises.advisors.destroy');
    Route::post('/enterprises/{enterprise:public_id}/members', [EnterpriseController::class, 'storeMember'])->name('enterprises.members.store');
    Route::post('/enterprises/{enterprise:public_id}/members/create', [EnterpriseController::class, 'storeCreatedMember'])->name('enterprises.members.create');
    Route::delete('/enterprises/{enterprise:public_id}/members/{user}', [EnterpriseController::class, 'destroyMember'])->name('enterprises.members.destroy');
});
