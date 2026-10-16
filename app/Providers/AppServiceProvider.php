<?php

namespace App\Providers;

use App\Http\Middleware\ArabicThrottleRequests;
use App\Http\Middleware\EnsureUserHasRole;
use App\Models\FinancialTransaction;
use App\Navigation\PageAccess;
use App\Policies\TransactionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ThrottleRequests::class, ArabicThrottleRequests::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(FinancialTransaction::class, TransactionPolicy::class);
        PageAccess::register();

        $this->app->make('router')->aliasMiddleware('role', EnsureUserHasRole::class);

        $this->app->booted(function () {
            if (! Route::has('login')) {
                Route::middleware('web')->group(base_path('routes/auth.php'));
            }
        });
    }
}
