<?php

namespace App\Ai;

use App\Ai\Contracts\AiProvider;
use App\Ai\Drivers\FakeAiProvider;
use App\Ai\Drivers\NullAiProvider;
use App\Ai\Models\AiEscalation;
use App\Ai\Models\KnowledgeArticle;
use App\Ai\Policies\AiEscalationPolicy;
use App\Ai\Policies\KnowledgeArticlePolicy;
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
        $this->app->singleton(AiProvider::class, fn () => match (config('ai.driver')) {
            'fake' => new FakeAiProvider,
            // TODO: BUSINESS DECISION REQUIRED — add the approved vendor adapter here.
            default => new NullAiProvider,
        });
    }

    public function boot(): void
    {
        Gate::policy(KnowledgeArticle::class, KnowledgeArticlePolicy::class);
        Gate::policy(AiEscalation::class, AiEscalationPolicy::class);

        RateLimiter::for('ai-assistant', fn (Request $r) => Limit::perMinute((int) config('ai.assistant_rate_per_minute', 10))
            ->by((string) ($r->user()?->id ?? $r->ip())));

        Route::middleware('web')->group(base_path('routes/ai.php'));
    }
}
