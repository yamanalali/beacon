<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Services\Operations\BackupService;
use Illuminate\Console\Command;

class BeaconBackup extends Command
{
    protected $signature = 'beacon:backup {--verify : run a restore test on the new backup}';

    protected $description = 'Create an encrypted backup of the database and private documents (section 56).';

    public function handle(BackupService $service): int
    {
        $run = $service->backup();
        if ($run->status !== BackupRun::STATUS_SUCCEEDED) {
            $this->error('Backup failed: '.$run->error);

            return self::FAILURE;
        }
        $this->info("Backup #{$run->id} stored at {$run->file_path}");

        if ($this->option('verify')) {
            $test = $service->restoreTest($run);
            $this->line('Restore test: '.$test->status);

            return $test->status === BackupRun::STATUS_SUCCEEDED ? self::SUCCESS : self::FAILURE;
        }

        return self::SUCCESS;
    }
}
