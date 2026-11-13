<?php

namespace App\Console\Commands;

use App\Actions\Baseline\ImportBaselineXlsxAction;
use Illuminate\Console\Command;

class ImportBaselineXlsx extends Command
{
    protected $signature = 'beacon:import-baseline {path : Absolute or relative path to the KoBo XLSX export}';

    protected $description = 'Import Baseline MSE questionnaire rows into enterprise profiles.';

    public function handle(ImportBaselineXlsxAction $action): int
    {
        $path = $this->argument('path');
        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            $path = base_path($path);
        }

        $this->info('Importing: '.$path);
        $result = $action->execute(null, $path);

        $this->table(
            ['created', 'updated', 'skipped', 'errors'],
            [[$result['created'], $result['updated'], $result['skipped'], count($result['errors'])]]
        );

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        return $result['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
