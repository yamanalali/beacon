<?php

namespace App\Providers;

use App\Console\Commands\SendComplianceDeadlineReminders;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/** Registers §43 reminders: routes, command and daily schedule. */
class NotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('web')->group(base_path('routes/notifications.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([SendComplianceDeadlineReminders::class]);
        }

        // TODO: BUSINESS DECISION REQUIRED — run time/timezone; interim daily 08:00 app timezone.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('reminders:compliance-deadlines')->dailyAt('08:00')->withoutOverlapping()->onOneServer();
        });
    }
}
