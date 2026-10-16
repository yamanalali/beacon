<?php

use App\Ai\Http\Controllers\AssistantController;
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
        Route::get('/knowledge/published', [KnowledgeArticleController::class, 'published'])->name('knowledge.published');
    });

    Route::middleware('role:beacon_admin')->prefix('admin/knowledge')->name('knowledge.')->group(function () {
        Route::get('/', [KnowledgeArticleController::class, 'index'])->name('index');
        Route::post('/', [KnowledgeArticleController::class, 'store'])->name('store');
        Route::put('/{article}', [KnowledgeArticleController::class, 'update'])->name('update');
        Route::post('/{article}/publish', [KnowledgeArticleController::class, 'publish'])->name('publish');
        Route::post('/{article}/archive', [KnowledgeArticleController::class, 'archive'])->name('archive');
    });
});
