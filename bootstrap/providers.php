<?php

use App\Ai\AiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FinanceServiceProvider;
use App\Providers\OperationsServiceProvider;
use App\Providers\NotificationServiceProvider;

return [
    AppServiceProvider::class,
    FinanceServiceProvider::class,
    OperationsServiceProvider::class,
    NotificationServiceProvider::class,
    // Registered after Finance so AI-backed bindings can replace the manual defaults.
    AiServiceProvider::class,
];
