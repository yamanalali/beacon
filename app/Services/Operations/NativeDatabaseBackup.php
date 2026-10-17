<?php

namespace App\Services\Operations;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/** Native, consistent dumps; restore checks always use a newly created disposable database. */
class NativeDatabaseBackup
{
    public function snapshot(Connection $connection, string $work): array
    {
        if ($connection->transactionLevel() > 0) {
            throw new RuntimeException('Backups cannot run inside an open database transaction.');
        }
        $driver = $connection->getDriverName();
        $config = $connection->getConfig();
        $file = $driver === 'pgsql' ? 'database.dump' : 'database.sql';
        $target = $work.'/'.$file;
        $tables = $connection->getSchemaBuilder()->getTableListing($driver === 'pgsql' ? null : $config['database'], $driver === 'pgsql');
        sort($tables);

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $defaults = $this->mysqlDefaults($config, $work);
            try {
                $binary = (string) config('operations.backup.native.'.$driver.'_dump');
                $args = [$binary, '--defaults-file='.$defaults, '--single-transaction', '--quick', '--hex-blob', '--no-tablespaces'];
                // MySQL's default GTID output can change server-global state when restored.
                $help = $this->run([$binary, '--help']);
                if (str_contains($help, 'set-gtid-purged')) {
                    $args[] = '--set-gtid-purged=OFF';
                }
                $this->run([...$args, '--result-file='.$target, '--', (string) $config['database']]);
            } finally {
                File::delete($defaults);
            }
        } elseif ($driver === 'pgsql') {
            $this->run([(string) config('operations.backup.native.pgsql_dump'), '--format=custom', '--no-owner', '--no-acl', '--file='.$target], $this->pgEnv($config));
        } else {
            throw new RuntimeException('Unsupported database backup driver: '.$driver);
        }
        if (! File::exists($target) || File::size($target) === 0) {
            throw new RuntimeException('Database dump is missing or empty.');
        }

        return ['driver' => $driver, 'file' => $file, 'sha256' => hash_file('sha256', $target), 'tables' => $tables];
    }

    public function restoreTest(array $snapshot, string $work): array
    {
        $driver = $snapshot['driver'] ?? '';
        if (! in_array($driver, ['mysql', 'mariadb', 'pgsql'], true)) {
            throw new RuntimeException('Unsupported native backup driver.');
        }
        $file = $driver === 'pgsql' ? 'database.dump' : 'database.sql';
        $source = $work.'/'.$file;
        if (($snapshot['file'] ?? null) !== $file || ! File::exists($source)
            || ! hash_equals((string) ($snapshot['sha256'] ?? ''), hash_file('sha256', $source))) {
            throw new RuntimeException('Database dump checksum mismatch.');
        }
        $control = DB::connection(config('operations.backup.restore_connection') ?: null);
        $mysql = in_array($driver, ['mysql', 'mariadb'], true);
        if ($mysql ? ! in_array($control->getDriverName(), ['mysql', 'mariadb'], true) : $control->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Restore-test connection must use the backup database engine.');
        }
        if ($control->transactionLevel() > 0) {
            throw new RuntimeException('Native restore tests cannot run inside a database transaction.');
        }

        // Name is generated here, never accepted from config, requests or a backup.
        // Dump commands omit --databases/--create, so they cannot select the live database.
        $database = 'beacon_restore_'.Str::lower(Str::random(20));
        $quoted = $mysql ? '`'.$database.'`' : '"'.$database.'"';
        $config = $control->getConfig();
        unset($config['url'], $config['name']);
        $config['database'] = $database;
        // Do not retain a read replica or a persistent connection to the live database.
        unset($config['read'], $config['write']);
        $config['options'][\PDO::ATTR_PERSISTENT] = false;
        $restored = null;
        $created = false;
        try {
            $control->statement('CREATE DATABASE '.$quoted);
            $created = true;
            if ($mysql) {
                $defaults = $this->mysqlDefaults($config, $work);
                try {
                    $this->run([(string) config('operations.backup.native.mysql_client'), '--defaults-file='.$defaults, '--database='.$database], [], File::get($source));
                } finally {
                    File::delete($defaults);
                }
            } else {
                $this->run([(string) config('operations.backup.native.pgsql_restore'), '--no-owner', '--no-acl', '--exit-on-error', '--dbname='.$database, $source], $this->pgEnv($config));
            }
            $restored = DB::build($config);
            $tables = $restored->getSchemaBuilder()->getTableListing($driver === 'pgsql' ? null : $database, $driver === 'pgsql');
            sort($tables);
            if ($tables !== $snapshot['tables']) {
                throw new RuntimeException('Restored table list does not match the backup manifest.');
            }
            $counts = [];
            foreach ($tables as $table) {
                $counts[$table] = $restored->table($table)->count();
            }

            return ['integrity_check' => 'ok', 'tables_checked' => count($tables), 'tables_mismatched' => [], 'restored_row_counts' => $counts];
        } finally {
            $restored?->disconnect();
            if ($created) {
                // Only the generated scratch database is ever dropped.
                $control->statement('DROP DATABASE '.$quoted);
            }
        }
    }

    private function mysqlDefaults(array $config, string $work): string
    {
        $path = $work.'/client-'.Str::random(12).'.cnf';
        $values = ['host' => $config['host'] ?? '127.0.0.1', 'port' => $config['port'] ?? 3306,
            'user' => $config['username'] ?? '', 'password' => $config['password'] ?? '', 'default-character-set' => $config['charset'] ?? 'utf8mb4'];
        if (! empty($config['unix_socket'])) {
            $values['socket'] = $config['unix_socket'];
        }
        $sslCaOption = defined('PDO::MYSQL_ATTR_SSL_CA') ? constant('PDO::MYSQL_ATTR_SSL_CA') : null;
        $ca = $sslCaOption !== null ? ($config['options'][$sslCaOption] ?? null) : null;
        if ($ca !== null) {
            $values['ssl-ca'] = $ca;
        }
        $text = "[client]\n";
        foreach ($values as $key => $value) {
            $escaped = str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], (string) $value);
            $text .= $key.'="'.$escaped."\"\n";
        }
        File::put($path, $text);
        chmod($path, 0600);

        return $path;
    }

    private function pgEnv(array $config): array
    {
        return ['PGHOST' => (string) ($config['host'] ?? '127.0.0.1'), 'PGPORT' => (string) ($config['port'] ?? 5432),
            'PGUSER' => (string) ($config['username'] ?? ''), 'PGPASSWORD' => (string) ($config['password'] ?? ''),
            'PGDATABASE' => (string) $config['database'], 'PGSSLMODE' => (string) ($config['sslmode'] ?? 'prefer')];
    }

    private function run(array $command, array $env = [], ?string $input = null): string
    {
        $process = Process::timeout((int) config('operations.backup.native.timeout', 300))->env($env);
        if ($input !== null) {
            $process = $process->input($input);
        }
        $result = $process->run($command);
        if (! $result->successful()) {
            // Do not include command output, config or connection credentials in stored errors.
            throw new RuntimeException('Database backup command failed: '.basename($command[0]).'. Check client installation, database permissions and server logs.');
        }

        return $result->output();
    }
}
