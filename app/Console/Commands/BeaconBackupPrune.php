<?php

namespace App\Console\Commands;

use App\Services\Operations\BackupService;
use Illuminate\Console\Command;
use RuntimeException;

class BeaconBackupPrune extends Command
{
    protected $signature = 'beacon:backup:prune';

    protected $description = 'Delete backups older than the approved retention period (refuses until a period is approved).';

    public function handle(BackupService $service): int
    {
        try {
            $this->info('Pruned '.$service->prune().' backup(s).');
        } catch (RuntimeException $e) {
            $this->warn($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
