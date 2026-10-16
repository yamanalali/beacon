<?php

use App\Providers\AppServiceProvider;
use App\Providers\FinanceServiceProvider;
use App\Providers\OperationsServiceProvider;
use App\Providers\NotificationServiceProvider;

return [
    AppServiceProvider::class,
    FinanceServiceProvider::class,
    OperationsServiceProvider::class,
    NotificationServiceProvider::class,
];
