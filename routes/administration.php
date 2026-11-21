<?php

use App\Http\Controllers\Administration\AccessController;
use App\Http\Controllers\Administration\AdministrationController;
use App\Http\Controllers\Administration\AuditLogController;
use App\Http\Controllers\Administration\BaselineFieldController;
use App\Http\Controllers\Administration\CurrencyController;
use App\Http\Controllers\Administration\InvitationController;
use App\Http\Controllers\Administration\TransactionCategoryController;
use App\Http\Controllers\Administration\UserProfileController;
use App\Http\Controllers\Administration\UserRoleController;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', EnsureUserIsActive::class, 'role:beacon_admin'])->group(function () {
    Route::get('/admin/baseline-fields', [BaselineFieldController::class, 'index'])->name('admin.baseline-fields.index');
    Route::put('/admin/baseline-fields/visibility', [BaselineFieldController::class, 'updateVisibility'])->name('admin.baseline-fields.visibility');
    Route::post('/admin/baseline-fields', [BaselineFieldController::class, 'store'])->name('admin.baseline-fields.store');
    Route::post('/admin/baseline-fields/{field}/hide', [BaselineFieldController::class, 'hide'])->whereNumber('field')->name('admin.baseline-fields.hide');
    Route::delete('/admin/baseline-fields/{field}', [BaselineFieldController::class, 'destroy'])->whereNumber('field')->name('admin.baseline-fields.destroy');
    Route::get('/admin/baseline/import', [BaselineFieldController::class, 'importForm'])->name('admin.baseline.import');
    Route::post('/admin/baseline/import', [BaselineFieldController::class, 'import'])->middleware('throttle:10,1')->name('admin.baseline.import.store');

    Route::get('/admin/currencies', [CurrencyController::class, 'index'])->name('admin.currencies.index');
    Route::post('/admin/currencies', [CurrencyController::class, 'store'])->name('admin.currencies.store');
    Route::put('/admin/currencies/{currency}', [CurrencyController::class, 'update'])->name('admin.currencies.update');

    Route::get('/admin/categories', [TransactionCategoryController::class, 'index'])->name('admin.categories.index');
    Route::post('/admin/categories', [TransactionCategoryController::class, 'store'])->name('admin.categories.store');
    Route::put('/admin/categories/{category}', [TransactionCategoryController::class, 'update'])->whereNumber('category')->name('admin.categories.update');
    Route::post('/admin/categories/{category}/deactivate', [TransactionCategoryController::class, 'deactivate'])->whereNumber('category')->name('admin.categories.deactivate');

    Route::get('/users/{user}/access', [AccessController::class, 'show'])->name('users.access.show');
    Route::put('/users/{user}/access', [AccessController::class, 'update'])->name('users.access.update');
    Route::get('/admin/invitations', [InvitationController::class, 'index'])->name('admin.invitations.index');
    Route::post('/admin/invitations', [InvitationController::class, 'store'])->middleware('throttle:20,1')->name('admin.invitations.store');
    Route::post('/admin/invitations/{invitation}/revoke', [InvitationController::class, 'revoke'])->name('admin.invitations.revoke');
    Route::get('/admin', [AdministrationController::class, 'index'])->name('admin.index');

    Route::put('/users/{user}/role', [UserRoleController::class, 'update'])->name('users.role.update');
    Route::put('/users/{user}/profile', [UserProfileController::class, 'update'])->name('users.profile.update');

    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
    Route::get('/admin/audit-logs/export', [AuditLogController::class, 'export'])
        ->middleware('throttle:10,1')
        ->name('admin.audit-logs.export');
    Route::get('/admin/audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('admin.audit-logs.show');
});
