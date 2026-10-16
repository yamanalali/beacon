<?php

namespace Tests\Unit\Operations;

use App\Services\Operations\NativeDatabaseBackup;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class NativeDatabaseBackupTest extends TestCase
{
    private string $work;

    protected function setUp(): void
    {
        parent::setUp();
        $this->work = sys_get_temp_dir().'/beacon-native-test-'.Str::random(12);
        File::makeDirectory($this->work, 0700, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->work);
        parent::tearDown();
    }

    private function connection(string $driver): Connection
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('getDriverName')->andReturn($driver);
        $connection->shouldReceive('transactionLevel')->andReturn(0);
        $connection->shouldReceive('getConfig')->andReturn(['name' => 'live_connection', 'driver' => $driver, 'host' => '127.0.0.1', 'database' => 'live_beacon', 'username' => 'operator', 'password' => 'private-test-password']);
        $schema = Mockery::mock(Builder::class);
        $schema->shouldReceive('getTableListing')->andReturn(['users']);
        $connection->shouldReceive('getSchemaBuilder')->andReturn($schema);

        return $connection;
    }

    public static function drivers(): array
    {
        return [['mysql'], ['mariadb'], ['pgsql']];
    }

    #[DataProvider('drivers')]
    public function test_native_snapshot_writes_a_checksummed_dump_without_credentials_in_arguments(string $driver): void
    {
        Process::fake(function (PendingProcess $process) {
            $this->assertStringNotContainsString('private-test-password', implode(' ', $process->command));
            if (in_array('--help', $process->command, true)) {
                return Process::result(output: 'set-gtid-purged');
            }
            foreach ($process->command as $argument) {
                if (str_starts_with($argument, '--file=') || str_starts_with($argument, '--result-file=')) {
                    File::put(explode('=', $argument, 2)[1], 'native dump contents');
                }
                if (str_starts_with($argument, '--defaults-file=')) {
                    $defaults = explode('=', $argument, 2)[1];
                    $this->assertSame(0600, fileperms($defaults) & 0777);
                    $this->assertStringContainsString('private-test-password', File::get($defaults));
                }
            }

            return Process::result();
        });
        $snapshot = app(NativeDatabaseBackup::class)->snapshot($this->connection($driver), $this->work);
        $this->assertSame($driver, $snapshot['driver']);
        $this->assertSame(hash('sha256', 'native dump contents'), $snapshot['sha256']);
        $this->assertSame([], glob($this->work.'/*.cnf'));
        Process::assertRan(fn ($p) => ! in_array('--help', $p->command, true) && ! in_array('--databases', $p->command, true) && ! in_array('--create', $p->command, true));
    }

    public function test_failed_dump_removes_credentials_and_does_not_store_process_output(): void
    {
        Process::fake(fn () => Process::result(errorOutput: 'private-test-password', exitCode: 1));
        try {
            app(NativeDatabaseBackup::class)->snapshot($this->connection('mysql'), $this->work);
            $this->fail('Failed dump was accepted.');
        } catch (RuntimeException $e) {
            $this->assertStringNotContainsString('private-test-password', $e->getMessage());
            $this->assertSame([], glob($this->work.'/*.cnf'));
        }
    }

    #[DataProvider('drivers')]
    public function test_restore_creates_and_drops_only_a_generated_database_even_on_failure(string $driver): void
    {
        $file = $driver === 'pgsql' ? 'database.dump' : 'database.sql';
        File::put($this->work.'/'.$file, 'native dump contents');
        $snapshot = ['driver' => $driver, 'file' => $file, 'sha256' => hash('sha256', 'native dump contents'), 'tables' => ['users']];
        $control = $this->connection($driver);
        $generated = null;
        $control->shouldReceive('statement')->once()->with(Mockery::on(function ($sql) use (&$generated) {
            if (! preg_match('/^CREATE DATABASE [`"](beacon_restore_[a-z0-9]{20})[`"]$/', $sql, $match)) {
                return false;
            }
            $generated = $match[1];

            return true;
        }))->andReturn(true);
        $control->shouldReceive('statement')->once()->with(Mockery::on(function ($sql) use (&$generated) {
            return $generated !== null && str_contains($sql, $generated) && str_starts_with($sql, 'DROP DATABASE ');
        }))->andReturn(true);
        DB::shouldReceive('connection')->once()->andReturn($control);
        Process::fake(function (PendingProcess $process) use (&$generated, $driver) {
            $this->assertStringContainsString($generated, implode(' ', $process->command));
            $this->assertStringNotContainsString('live_beacon', implode(' ', $process->command));
            if ($driver === 'pgsql') {
                $this->assertSame($generated, $process->environment['PGDATABASE']);
            }

            return Process::result(exitCode: 1);
        });
        $this->expectException(RuntimeException::class);
        app(NativeDatabaseBackup::class)->restoreTest($snapshot, $this->work);
    }

    #[DataProvider('drivers')]
    public function test_successful_restore_uses_a_separate_connection_and_verifies_tables(string $driver): void
    {
        $file = $driver === 'pgsql' ? 'database.dump' : 'database.sql';
        File::put($this->work.'/'.$file, 'native dump contents');
        $snapshot = ['driver' => $driver, 'file' => $file, 'sha256' => hash('sha256', 'native dump contents'), 'tables' => ['users']];
        $control = $this->connection($driver);
        $control->shouldReceive('statement')->twice()->andReturn(true);
        $restored = $this->connection($driver);
        $restored->shouldReceive('table')->with('users')->andReturnSelf();
        $restored->shouldReceive('count')->andReturn(7);
        $restored->shouldReceive('disconnect')->once();
        DB::shouldReceive('connection')->once()->andReturn($control);
        DB::shouldReceive('build')->once()->with(Mockery::on(function ($config) {
            $this->assertArrayNotHasKey('name', $config);
            $this->assertArrayNotHasKey('url', $config);
            $this->assertStringStartsWith('beacon_restore_', $config['database']);

            return true;
        }))->andReturn($restored);
        Process::fake();
        $result = app(NativeDatabaseBackup::class)->restoreTest($snapshot, $this->work);
        $this->assertSame('ok', $result['integrity_check']);
        $this->assertSame(['users' => 7], $result['restored_row_counts']);
    }

    public function test_tampered_dump_is_rejected_before_any_database_or_process_is_used(): void
    {
        File::put($this->work.'/database.dump', 'tampered');
        Process::fake();
        DB::shouldReceive('connection')->never();
        try {
            app(NativeDatabaseBackup::class)->restoreTest(['driver' => 'pgsql', 'file' => 'database.dump', 'sha256' => hash('sha256', 'original')], $this->work);
            $this->fail('Tampered dump was accepted.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('checksum', $e->getMessage());
            Process::assertNothingRan();
        }
    }
}
