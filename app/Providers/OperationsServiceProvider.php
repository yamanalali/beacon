<?php

namespace App\Providers;

use App\Console\Commands\BeaconBackup;
use App\Console\Commands\BeaconBackupPrune;
use App\Console\Commands\BeaconBackupRestoreTest;
use App\Http\Middleware\EnforceHttps;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

/** Sections 53, 54, 56 wiring kept out of shared bootstrap files. */
class OperationsServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class)->prependMiddlewareToGroup('web', EnforceHttps::class);

        if (config('operations.force_https')) {
            URL::forceScheme('https');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([BeaconBackup::class, BeaconBackupRestoreTest::class, BeaconBackupPrune::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('beacon:backup')
                ->dailyAt((string) config('operations.backup.time'))
                ->when(fn () => (bool) config('operations.backup.enabled'))
                ->withoutOverlapping()
                ->onOneServer();

            // Only prunes once Beacon approves a retention period.
            $schedule->command('beacon:backup:prune')
                ->daily()
                ->when(fn () => (bool) config('operations.backup.enabled') && config('operations.backup.retention_days') !== null);
        });
    }
}
