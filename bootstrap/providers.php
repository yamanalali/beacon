<?php

use App\Ai\AiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FinanceServiceProvider;

return [
    AppServiceProvider::class,
    FinanceServiceProvider::class,
    // Registered after Finance so AI-backed bindings can replace the manual defaults.
    AiServiceProvider::class,
];
