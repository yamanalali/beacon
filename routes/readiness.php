<?php

use App\Http\Controllers\Readiness\ComplianceAdminController;
use App\Http\Controllers\Readiness\EnterpriseComplianceController;
use App\Http\Controllers\Readiness\MfiController;
use App\Http\Controllers\Readiness\ReadinessAdminController;
use App\Http\Controllers\Readiness\ReadinessAssessmentController;
use App\Http\Controllers\Readiness\ReferralController;
use Illuminate\Support\Facades\Route;

// Beacon sections 20-31. Every action authorizes inside the controller via App\Support\ReadinessAccess.
Route::middleware('auth')->group(function () {
    // 20-21, 23: compliance configuration (BEACON_ADMIN)
    Route::prefix('admin/compliance')->name('admin.compliance.')->group(function () {
        Route::get('/', [ComplianceAdminController::class, 'index'])->name('index');
        Route::post('/', [ComplianceAdminController::class, 'store'])->name('store');
        Route::post('/{requirement:public_id}/versions', [ComplianceAdminController::class, 'storeVersion'])->name('versions.store');
        Route::post('/{requirement:public_id}/deactivate', [ComplianceAdminController::class, 'deactivate'])->name('deactivate');
        Route::post('/{requirement:public_id}/replace', [ComplianceAdminController::class, 'replace'])->name('replace');
        Route::put('/versions/{version}', [ComplianceAdminController::class, 'updateVersion'])->name('versions.update');
        Route::post('/versions/{version}/publish', [ComplianceAdminController::class, 'publish'])->name('versions.publish');
    });

    // 22: enterprise compliance view
    Route::get('/enterprises/{enterprise:public_id}/compliance', [EnterpriseComplianceController::class, 'index'])->name('enterprises.compliance.index');
    Route::put('/enterprises/{enterprise:public_id}/compliance/{requirement:public_id}', [EnterpriseComplianceController::class, 'update'])->name('enterprises.compliance.update')->withoutScopedBindings();

    // 25-26: readiness rules (BEACON_ADMIN)
    Route::prefix('admin/readiness')->name('admin.readiness.')->group(function () {
        Route::get('/', [ReadinessAdminController::class, 'index'])->name('index');
        Route::post('/', [ReadinessAdminController::class, 'store'])->name('store');
        Route::post('/{ruleSet}/criteria', [ReadinessAdminController::class, 'storeCriterion'])->name('criteria.store');
        Route::delete('/criteria/{criterion}', [ReadinessAdminController::class, 'destroyCriterion'])->name('criteria.destroy');
        Route::post('/{ruleSet}/publish', [ReadinessAdminController::class, 'publish'])->name('publish');
    });

    // 24: readiness assessments
    Route::get('/enterprises/{enterprise:public_id}/readiness', [ReadinessAssessmentController::class, 'index'])->name('enterprises.readiness.index');
    Route::post('/enterprises/{enterprise:public_id}/readiness', [ReadinessAssessmentController::class, 'store'])->name('enterprises.readiness.store');
    Route::get('/readiness-assessments/{assessment:public_id}', [ReadinessAssessmentController::class, 'show'])->name('readiness-assessments.show');
    Route::put('/readiness-assessments/{assessment:public_id}/results/{result}', [ReadinessAssessmentController::class, 'updateResult'])->name('readiness-assessments.results.update');
    Route::post('/readiness-assessments/{assessment:public_id}/finalize', [ReadinessAssessmentController::class, 'finalize'])->name('readiness-assessments.finalize');
    Route::post('/readiness-assessments/{assessment:public_id}/recalculate', [ReadinessAssessmentController::class, 'recalculate'])->name('readiness-assessments.recalculate');

    // 27: MFI directory (BEACON_ADMIN)
    Route::prefix('admin/mfis')->name('admin.mfis.')->group(function () {
        Route::get('/', [MfiController::class, 'index'])->name('index');
        Route::post('/', [MfiController::class, 'store'])->name('store');
        Route::put('/{institution:public_id}', [MfiController::class, 'update'])->name('update');
        Route::post('/{institution:public_id}/criteria', [MfiController::class, 'storeCriterion'])->name('criteria.store');
        Route::post('/{institution:public_id}/users', [MfiController::class, 'attachUser'])->name('users.attach');
        Route::post('/criteria/{criterion}/deactivate', [MfiController::class, 'deactivateCriterion'])->name('criteria.deactivate');
    });

    // 28: MFI matching
    Route::get('/enterprises/{enterprise:public_id}/mfi-matching', [MfiController::class, 'matching'])->name('enterprises.mfi-matching');

    // 29-31: referrals & consent
    Route::get('/enterprises/{enterprise:public_id}/referrals', [ReferralController::class, 'index'])->name('enterprises.referrals.index');
    Route::post('/enterprises/{enterprise:public_id}/referrals', [ReferralController::class, 'store'])->name('enterprises.referrals.store');
    Route::get('/referrals/{referral:public_id}', [ReferralController::class, 'show'])->name('referrals.show');
    Route::post('/referrals/{referral:public_id}/consent', [ReferralController::class, 'consent'])->name('referrals.consent');
    Route::post('/referrals/{referral:public_id}/consent/withdraw', [ReferralController::class, 'withdrawConsent'])->name('referrals.consent.withdraw');
    Route::post('/referrals/{referral:public_id}/share', [ReferralController::class, 'share'])->name('referrals.share');
    Route::post('/referrals/{referral:public_id}/status', [ReferralController::class, 'updateStatus'])->name('referrals.status');
    Route::post('/referrals/{referral:public_id}/outcome', [ReferralController::class, 'recordOutcome'])->name('referrals.outcome');
    Route::get('/mfi/referrals', [ReferralController::class, 'mfiInbox'])->name('mfi.referrals.index');
});
