<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Services\Operations\BackupService;
use Illuminate\Console\Command;

class BeaconBackupRestoreTest extends Command
{
    protected $signature = 'beacon:backup:restore-test {backup? : backup run id (defaults to latest successful)}';

    protected $description = 'Restore a backup into an isolated temporary copy and verify it. Never touches the live database.';

    public function handle(BackupService $service): int
    {
        $backup = $this->argument('backup')
            ? BackupRun::query()->findOrFail($this->argument('backup'))
            : BackupRun::query()->where('type', BackupRun::TYPE_BACKUP)->where('status', BackupRun::STATUS_SUCCEEDED)->whereNull('pruned_at')->latest('id')->firstOrFail();

        $run = $service->restoreTest($backup);
        $this->line(json_encode($run->details, JSON_UNESCAPED_UNICODE));
        $run->status === BackupRun::STATUS_SUCCEEDED ? $this->info('Restore test succeeded.') : $this->error('Restore test failed: '.$run->error);

        return $run->status === BackupRun::STATUS_SUCCEEDED ? self::SUCCESS : self::FAILURE;
    }
}
