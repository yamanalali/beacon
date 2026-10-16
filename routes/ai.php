<?php

use App\Ai\Http\Controllers\AiPageController;
use App\Ai\Http\Controllers\AssistantController;
use App\Ai\Http\Controllers\FinanceAiController;
use App\Ai\Http\Controllers\KnowledgeArticleController;
use Illuminate\Support\Facades\Route;

// AI assistant, escalation and knowledge base (rules §33–§37). JSON endpoints.
Route::middleware('auth')->prefix('ai')->name('ai.')->group(function () {
    Route::middleware('role:sme_user,beacon_advisor,beacon_admin')->group(function () {
        Route::post('/assistant/ask', [AssistantController::class, 'ask'])
            ->middleware('throttle:ai-assistant')->name('assistant.ask');
        Route::post('/escalations', [AssistantController::class, 'escalate'])
            ->middleware('throttle:ai-assistant')->name('escalations.store');
        Route::get('/escalations', [AssistantController::class, 'escalations'])->name('escalations.index');
        Route::get('/escalations/{escalation}', [AssistantController::class, 'showEscalation'])->name('escalations.show');
        // §16 review flags and §19 insights; enterprise access via reports.view.
        Route::get('/enterprises/{enterprise:public_id}/review-flags', [FinanceAiController::class, 'flags'])->name('finance.flags');
        Route::get('/enterprises/{enterprise:public_id}/insights', [FinanceAiController::class, 'insights'])
            ->middleware('throttle:ai-insights')->name('finance.insights');
        Route::get('/knowledge/published', [KnowledgeArticleController::class, 'published'])->name('knowledge.published');

        // Inertia pages (frontend builds the components).
        Route::get('/pages/assistant', [AiPageController::class, 'assistant'])->name('pages.assistant');
        Route::get('/pages/escalations', [AiPageController::class, 'escalations'])->name('pages.escalations.index');
        Route::get('/pages/escalations/{escalation}', [AiPageController::class, 'showEscalation'])->name('pages.escalations.show');
        Route::get('/pages/knowledge', [AiPageController::class, 'published'])->name('pages.knowledge.published');
    });

    Route::get('/admin/knowledge/page', [AiPageController::class, 'manage'])
        ->middleware('role:beacon_admin')->name('pages.knowledge.index');

    Route::middleware('role:beacon_admin')->prefix('admin/knowledge')->name('knowledge.')->group(function () {
        Route::get('/', [KnowledgeArticleController::class, 'index'])->name('index');
        Route::post('/', [KnowledgeArticleController::class, 'store'])->name('store');
        Route::put('/{article}', [KnowledgeArticleController::class, 'update'])->name('update');
        Route::post('/{article}/publish', [KnowledgeArticleController::class, 'publish'])->name('publish');
        Route::post('/{article}/archive', [KnowledgeArticleController::class, 'archive'])->name('archive');
    });
});
