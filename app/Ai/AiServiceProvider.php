<?php

namespace App\Ai;

use App\Ai\Contracts\AiProvider;
use App\Ai\Drivers\FakeAiProvider;
use App\Ai\Drivers\NullAiProvider;
use App\Ai\Drivers\OpenAiAiProvider;
use App\Ai\Drivers\OpenRouterAiProvider;
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

        $this->app->singleton(AiProvider::class, function () {
            return match (config('ai.driver')) {
                'fake' => new FakeAiProvider,
                'openai' => new OpenAiAiProvider(
                    apiKey: (string) config('ai.openai.api_key', ''),
                    baseUrl: (string) config('ai.openai.base_url', 'https://api.openai.com/v1'),
                    chatModel: (string) config('ai.openai.chat_model', 'gpt-6-luna'),
                    visionModel: (string) config('ai.openai.vision_model', 'gpt-6-luna'),
                    transcriptionModel: (string) config('ai.openai.transcription_model', 'gpt-4o-mini-transcribe'),
                    timeout: (int) config('ai.openai.timeout', 60),
                    visionMaxEdge: (int) config('ai.openai.vision_max_edge', 1280),
                    visionJpegQuality: (int) config('ai.openai.vision_jpeg_quality', 72),
                    visionDetail: (string) config('ai.openai.vision_detail', 'low'),
                    maxTokensChat: (int) config('ai.openai.max_tokens_chat', 400),
                    maxTokensVision: (int) config('ai.openai.max_tokens_vision', 220),
                    verifySsl: config('ai.openai.ca_bundle') ?: true,
                ),
                'openrouter' => new OpenRouterAiProvider(
                    apiKey: (string) config('ai.openrouter.api_key', ''),
                    baseUrl: (string) config('ai.openrouter.base_url', 'https://openrouter.ai/api/v1'),
                    chatModel: (string) config('ai.openrouter.chat_model', 'nvidia/nemotron-3.5-lightning:free'),
                    visionModel: (string) config('ai.openrouter.vision_model', 'google/gemma-4-26b-a4b-it:free'),
                    transcriptionModel: (string) config('ai.openrouter.transcription_model', 'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free'),
                    transcriptionMode: (string) config('ai.openrouter.transcription_mode', 'chat'),
                    timeout: (int) config('ai.openrouter.timeout', 90),
                    httpReferer: config('ai.openrouter.http_referer'),
                    appTitle: config('ai.openrouter.app_title'),
                    verifySsl: config('ai.openrouter.ca_bundle') ?: true,
                ),
                default => new NullAiProvider,
            };
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

        RateLimiter::for('ai-assistant', fn (Request $r) => Limit::perMinute((int) config('ai.assistant_rate_per_minute', 8))
            ->by((string) ($r->user()?->id ?? $r->ip())));

        RateLimiter::for('ai-insights', fn (Request $r) => Limit::perMinute((int) config('ai.assistant_rate_per_minute', 8))
            ->by((string) ($r->user()?->id ?? $r->ip())));

        RateLimiter::for('ai-ocr', fn (Request $r) => Limit::perMinute((int) config('ai.ocr_rate_per_minute', 6))
            ->by((string) ($r->user()?->id ?? $r->ip())));

        RateLimiter::for('ai-voice', fn (Request $r) => Limit::perMinute((int) config('ai.voice_rate_per_minute', 6))
            ->by((string) ($r->user()?->id ?? $r->ip())));

        Route::middleware('web')->group(base_path('routes/ai.php'));
    }
}
