<?php

use App\Http\Controllers\Administration\AccessController;
use App\Http\Controllers\Administration\AdministrationController;
use App\Http\Controllers\Administration\AuditLogController;
use App\Http\Controllers\Administration\CurrencyController;
use App\Http\Controllers\Administration\InvitationController;
use App\Http\Controllers\Administration\UserRoleController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureUserIsActive::class, 'role:beacon_admin'])->group(function () {
    Route::get('/admin/currencies', [CurrencyController::class, 'index'])->name('admin.currencies.index');
    Route::post('/admin/currencies', [CurrencyController::class, 'store'])->name('admin.currencies.store');
    Route::put('/admin/currencies/{currency}', [CurrencyController::class, 'update'])->name('admin.currencies.update');

    Route::get('/users/{user}/access', [AccessController::class, 'show'])->name('users.access.show');
    Route::put('/users/{user}/access', [AccessController::class, 'update'])->name('users.access.update');
    Route::get('/admin/invitations', [InvitationController::class, 'index'])->name('admin.invitations.index');
    Route::post('/admin/invitations', [InvitationController::class, 'store'])->middleware('throttle:20,1')->name('admin.invitations.store');
    Route::post('/admin/invitations/{invitation}/revoke', [InvitationController::class, 'revoke'])->name('admin.invitations.revoke');
    Route::get('/admin', [AdministrationController::class, 'index'])->name('admin.index');

    Route::put('/users/{user}/role', [UserRoleController::class, 'update'])->name('users.role.update');

    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
    Route::get('/admin/audit-logs/export', [AuditLogController::class, 'export'])
        ->middleware('throttle:10,1')
        ->name('admin.audit-logs.export');
    Route::get('/admin/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('admin.audit-logs.show');
});
