<?php

namespace App\Services\Operations;

use App\Models\BackupRun;
use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Section 56 (and 53 "protected stored backups"): encrypted backups of the database and private documents,
 * plus an automated restore test that restores into an isolated temporary copy (never the live database).
 *
 * The automated restore test does NOT replace the formal restoration exercise in a separate environment
 * required before final acceptance; see docs/operations/backup-and-restore.md.
 */
class BackupService
{
    public function backup(?User $actor = null): BackupRun
    {
        $run = BackupRun::query()->create([
            'type' => BackupRun::TYPE_BACKUP, 'status' => BackupRun::STATUS_RUNNING,
            'triggered_by' => $actor?->id, 'started_at' => now(),
        ]);
        $work = $this->tempDir();

        try {
            $this->snapshotDatabase($work.'/database.sqlite');
            $documents = $this->copyDocuments($work.'/documents');
            $manifest = [
                'format' => 1,
                'created_at' => now()->toIso8601String(),
                'tables' => $this->rowCounts(new PDO('sqlite:'.$work.'/database.sqlite')),
                'documents' => $documents,
            ];
            File::put($work.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

            $zipPath = $work.'.zip';
            $this->zip($work, $zipPath);
            $encrypted = $this->encrypter()->encryptString(base64_encode(File::get($zipPath)));
            File::delete($zipPath);

            $relative = trim(config('operations.backup.path'), '/').'/beacon-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.bak';
            $this->disk()->put($relative, $encrypted);

            $run->update([
                'status' => BackupRun::STATUS_SUCCEEDED,
                'file_path' => $relative,
                'size_bytes' => strlen($encrypted),
                'checksum_sha256' => hash('sha256', $encrypted),
                'details' => ['tables' => count($manifest['tables']), 'documents' => count($documents)],
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $run->update(['status' => BackupRun::STATUS_FAILED, 'error' => $e->getMessage(), 'finished_at' => now()]);
        } finally {
            File::deleteDirectory($work);
        }

        return $run->fresh();
    }

    public function restoreTest(BackupRun $backup, ?User $actor = null): BackupRun
    {
        $run = BackupRun::query()->create([
            'type' => BackupRun::TYPE_RESTORE_TEST, 'status' => BackupRun::STATUS_RUNNING,
            'backup_run_id' => $backup->id, 'triggered_by' => $actor?->id, 'started_at' => now(),
        ]);
        $work = $this->tempDir();

        try {
            if ($backup->type !== BackupRun::TYPE_BACKUP || $backup->status !== BackupRun::STATUS_SUCCEEDED || $backup->pruned_at !== null) {
                throw new RuntimeException('Selected run is not an available successful backup.');
            }
            $encrypted = $this->disk()->get($backup->file_path);
            if ($encrypted === null || ! hash_equals((string) $backup->checksum_sha256, hash('sha256', $encrypted))) {
                throw new RuntimeException('Backup file missing or checksum mismatch.');
            }
            File::put($work.'.zip', base64_decode($this->encrypter()->decryptString($encrypted)));
            $zip = new ZipArchive;
            if ($zip->open($work.'.zip') !== true) {
                throw new RuntimeException('Backup archive cannot be opened.');
            }
            $zip->extractTo($work);
            $zip->close();
            File::delete($work.'.zip');

            $manifest = json_decode(File::get($work.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            $pdo = new PDO('sqlite:'.$work.'/database.sqlite');
            $integrity = $pdo->query('PRAGMA integrity_check')->fetchColumn();
            $counts = $this->rowCounts($pdo);
            $pdo = null;

            $mismatched = [];
            foreach ($manifest['tables'] as $table => $count) {
                if (($counts[$table] ?? null) !== $count) {
                    $mismatched[] = $table;
                }
            }
            $missingDocs = array_values(array_filter($manifest['documents'], fn ($d) => ! File::exists($work.'/documents/'.$d['path'])
                || hash_file('sha256', $work.'/documents/'.$d['path']) !== $d['sha256']));

            $ok = $integrity === 'ok' && $mismatched === [] && $missingDocs === [];
            $run->update([
                'status' => $ok ? BackupRun::STATUS_SUCCEEDED : BackupRun::STATUS_FAILED,
                'details' => [
                    'integrity_check' => $integrity,
                    'tables_checked' => count($manifest['tables']),
                    'tables_mismatched' => $mismatched,
                    'documents_checked' => count($manifest['documents']),
                    'documents_failed' => array_column($missingDocs, 'path'),
                ],
                'error' => $ok ? null : 'Restored copy did not match the backup manifest.',
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            $run->update(['status' => BackupRun::STATUS_FAILED, 'error' => $e->getMessage(), 'finished_at' => now()]);
        } finally {
            File::deleteDirectory($work);
            File::delete($work.'.zip');
        }

        return $run->fresh();
    }

    /**
     * Deletes backup files older than the approved retention period. Refuses to run until Beacon approves a period.
     * Always keeps the newest successful backup.
     *
     * @return int number of pruned backups
     */
    public function prune(): int
    {
        $days = config('operations.backup.retention_days');
        if ($days === null) {
            throw new RuntimeException('Backup retention period is not approved yet (TODO: BUSINESS DECISION REQUIRED).');
        }
        $newest = BackupRun::query()->where('type', BackupRun::TYPE_BACKUP)->where('status', BackupRun::STATUS_SUCCEEDED)->whereNull('pruned_at')->latest('id')->value('id');
        $old = BackupRun::query()->where('type', BackupRun::TYPE_BACKUP)->where('status', BackupRun::STATUS_SUCCEEDED)
            ->whereNull('pruned_at')->where('id', '!=', $newest)->where('created_at', '<', now()->subDays((int) $days))->get();

        foreach ($old as $run) {
            $this->disk()->delete($run->file_path);
            $run->update(['pruned_at' => now()]);
        }

        return $old->count();
    }

    private function snapshotDatabase(string $target): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            // TODO: BUSINESS DECISION REQUIRED - production database engine/hosting; add the engine-native dump here.
            throw new RuntimeException('Only SQLite snapshots are implemented.');
        }
        if (DB::transactionLevel() > 0) {
            throw new RuntimeException('Backups cannot run inside an open database transaction.');
        }
        DB::statement('VACUUM INTO ?', [$target]);
    }

    /** @return list<array{path:string,sha256:string}> */
    private function copyDocuments(string $target): array
    {
        File::ensureDirectoryExists($target);
        if (! config('operations.backup.include_documents')) {
            return [];
        }
        $disk = Storage::disk(config('documents.disk', 'local'));
        $skip = trim(config('operations.backup.path'), '/').'/';
        $files = [];
        foreach ($disk->allFiles() as $path) {
            if (str_starts_with($path, $skip) || str_starts_with($path, '.')) {
                continue;
            }
            $contents = $disk->get($path);
            File::ensureDirectoryExists(dirname($target.'/'.$path));
            File::put($target.'/'.$path, $contents);
            $files[] = ['path' => $path, 'sha256' => hash('sha256', $contents)];
        }

        return $files;
    }

    /** @return array<string,int> */
    private function rowCounts(PDO $pdo): array
    {
        $counts = [];
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM "'.str_replace('"', '""', $table).'"')->fetchColumn();
        }

        return $counts;
    }

    private function zip(string $dir, string $zipPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create backup archive.');
        }
        foreach (File::allFiles($dir, true) as $file) {
            $zip->addFile($file->getPathname(), str_replace('\\', '/', $file->getRelativePathname()));
        }
        $zip->close();
    }

    private function encrypter(): Encrypter
    {
        $key = config('operations.backup.key') ?: config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return new Encrypter($key, config('app.cipher'));
    }

    private function disk()
    {
        return Storage::disk(config('operations.backup.disk'));
    }

    private function tempDir(): string
    {
        $dir = storage_path('app/backup-work/'.Str::random(16));
        File::ensureDirectoryExists($dir);

        return $dir;
    }
}
