<?php

namespace App\Ai;

use App\Ai\Contracts\AiProvider;
use App\Ai\Drivers\FakeAiProvider;
use App\Ai\Drivers\NullAiProvider;
use App\Ai\Finance\AiReceiptExtractor;
use App\Ai\Finance\AiTransactionCategorizer;
use App\Ai\Models\AiEscalation;
use App\Ai\Models\KnowledgeArticle;
use App\Ai\Ocr\TesseractReceiptExtractor;
use App\Ai\Policies\AiEscalationPolicy;
use App\Ai\Policies\KnowledgeArticlePolicy;
use App\Ai\Voice\ProviderTranscriber;
use App\Ai\Voice\SpeechToText;
use App\Ai\Voice\WhisperCppTranscriber;
use App\Contracts\ReceiptExtractor;
use App\Contracts\TransactionCategorizer;
use App\Services\Ai\ManualReceiptExtractor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerVoice();

        $this->app->singleton(AiProvider::class, fn () => match (config('ai.driver')) {
            'fake' => new FakeAiProvider,
            // TODO: BUSINESS DECISION REQUIRED — add the approved vendor adapter here.
            default => new NullAiProvider,
        });

        // Replace Finance's manual defaults only when an AI driver is enabled,
        // so with the default "null" driver the manual workflow is unchanged.
        if (config('ai.driver', 'null') !== 'null') {
            $this->app->bind(ReceiptExtractor::class, AiReceiptExtractor::class);
            $this->app->bind(TransactionCategorizer::class, AiTransactionCategorizer::class);
        }

        // Receipt OCR driver override (local Tesseract needs no DataControl approval).
        match (config('ai.ocr.driver')) {
            'tesseract' => $this->app->bind(ReceiptExtractor::class, TesseractReceiptExtractor::class),
            'ai' => $this->app->bind(ReceiptExtractor::class, AiReceiptExtractor::class),
            'manual' => $this->app->bind(ReceiptExtractor::class, ManualReceiptExtractor::class),
            default => null,
        };
    }

    private function registerVoice(): void
    {
        $this->app->bind(SpeechToText::class, fn ($app) => config('ai.voice.stt_driver') === 'provider'
            ? $app->make(ProviderTranscriber::class)
            : new WhisperCppTranscriber);
    }

    public function boot(): void
    {
        Gate::policy(KnowledgeArticle::class, KnowledgeArticlePolicy::class);
        Gate::policy(AiEscalation::class, AiEscalationPolicy::class);

        RateLimiter::for('ai-assistant', fn (Request $r) => Limit::perMinute((int) config('ai.assistant_rate_per_minute', 10))
            ->by((string) ($r->user()?->id ?? $r->ip())));

        RateLimiter::for('ai-insights', fn (Request $r) => Limit::perMinute((int) config('ai.assistant_rate_per_minute', 10))
            ->by((string) ($r->user()?->id ?? $r->ip())));

        Route::middleware('web')->group(base_path('routes/ai.php'));
    }
}
