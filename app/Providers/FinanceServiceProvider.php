<?php

namespace App\Providers;

use App\Contracts\ReceiptExtractor;
use App\Contracts\TransactionCategorizer;
use App\Policies\ReportPolicy;
use App\Services\Ai\ManualReceiptExtractor;
use App\Services\Ai\ManualTransactionCategorizer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Replace these bindings with real OCR / AI providers later.
        $this->app->bind(ReceiptExtractor::class, ManualReceiptExtractor::class);
        $this->app->bind(TransactionCategorizer::class, ManualTransactionCategorizer::class);
    }

    public function boot(): void
    {
        Gate::define('reports.view', [ReportPolicy::class, 'view']);
        Gate::define('reports.export', [ReportPolicy::class, 'export']);
    }
}
