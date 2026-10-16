<?php

use App\Http\Controllers\Administration\AdministrationController;
use App\Http\Controllers\Administration\AuditLogController;
use App\Http\Controllers\Administration\UserRoleController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureUserIsActive::class, 'role:beacon_admin'])->group(function () {
    Route::get('/admin', [AdministrationController::class, 'index'])->name('admin.index');

    Route::put('/users/{user}/role', [UserRoleController::class, 'update'])->name('users.role.update');

    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
    Route::get('/admin/audit-logs/export', [AuditLogController::class, 'export'])
        ->middleware('throttle:10,1')
        ->name('admin.audit-logs.export');
    Route::get('/admin/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('admin.audit-logs.show');
});
