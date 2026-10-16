<?php

namespace Tests\Feature\Operations;

use App\Enums\UserRole;
use App\Models\BackupRun;
use App\Models\User;
use App\Services\Operations\BackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Uses DatabaseMigrations: SQLite cannot VACUUM INTO inside the RefreshDatabase transaction. */
class BackupTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->forceFill(['is_active' => true])->save();

        return $user->fresh();
    }

    public function test_backup_is_encrypted_and_restore_test_verifies_database_and_documents(): void
    {
        $tech = $this->user(UserRole::TechnicalAdmin);
        Storage::disk('local')->put('documents/1/receipt.pdf', 'PDF-CONTENT-سري');

        $this->actingAs($tech)->post(route('operations.backups.run'))->assertRedirect(route('operations.backups'));
        $backup = BackupRun::query()->where('type', 'backup')->firstOrFail();
        $this->assertSame('succeeded', $backup->status, (string) $backup->error);
        $this->assertSame(1, $backup->details['documents']);

        $stored = Storage::disk('local')->get($backup->file_path);
        $this->assertStringNotContainsString('PDF-CONTENT', $stored);
        $this->assertStringNotContainsString('SQLite format', $stored);
        $this->assertDatabaseHas('audit_logs', ['action' => 'operations.backup_run', 'actor_id' => $tech->id]);

        $this->actingAs($tech)->post(route('operations.backups.restore-test', $backup))->assertRedirect();
        $test = BackupRun::query()->where('type', 'restore_test')->firstOrFail();
        $this->assertSame('succeeded', $test->status, (string) $test->error);
        $this->assertSame('ok', $test->details['integrity_check']);
        $this->assertSame([], $test->details['tables_mismatched']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'operations.restore_test_run']);
    }

    public function test_tampered_backup_fails_restore_test(): void
    {
        $service = app(BackupService::class);
        $backup = $service->backup();
        Storage::disk('local')->put($backup->file_path, 'tampered');

        $this->assertSame('failed', $service->restoreTest($backup)->status);
    }

    public function test_backup_with_wrong_key_cannot_be_restored(): void
    {
        $service = app(BackupService::class);
        $backup = $service->backup();
        config(['operations.backup.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $this->assertSame('failed', $service->restoreTest($backup)->status);
    }

    public function test_prune_refuses_without_approved_retention_and_keeps_newest(): void
    {
        $service = app(BackupService::class);
        $this->artisan('beacon:backup:prune')->assertFailed();

        $old = $service->backup();
        BackupRun::query()->whereKey($old->id)->update(['created_at' => now()->subDays(40)]);
        $newest = $service->backup();
        config(['operations.backup.retention_days' => 30]);

        $this->assertSame(1, $service->prune());
        $this->assertNotNull($old->fresh()->pruned_at);
        $this->assertFalse(Storage::disk('local')->exists($old->file_path));
        $this->assertNull($newest->fresh()->pruned_at);
    }

    public function test_backup_command_with_verify(): void
    {
        $this->artisan('beacon:backup --verify')->assertSuccessful();
        $this->assertSame(2, BackupRun::query()->where('status', 'succeeded')->count());
    }

    public function test_restore_test_never_touches_live_database(): void
    {
        $service = app(BackupService::class);
        $backup = $service->backup();
        $liveUser = $this->user(UserRole::SmeUser); // created AFTER the backup
        $usersBefore = User::query()->count();
        $database = config('database.connections.sqlite.database');

        $run = $service->restoreTest($backup);

        $this->assertSame('succeeded', $run->status, (string) $run->error);
        $this->assertSame($usersBefore, User::query()->count());
        $this->assertNotNull(User::query()->find($liveUser->id));
        $this->assertSame($database, config('database.connections.sqlite.database'));
        $this->assertSame('sqlite', \Illuminate\Support\Facades\DB::getDefaultConnection());
    }

    public function test_restore_test_endpoint_is_technical_admin_only(): void
    {
        $backup = app(BackupService::class)->backup();
        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::BeaconAdmin, UserRole::MfiUser] as $role) {
            $this->actingAs($this->user($role))->post(route('operations.backups.restore-test', $backup))->assertForbidden();
        }
        $inactive = $this->user(UserRole::TechnicalAdmin);
        $inactive->forceFill(['is_active' => false])->save();
        $this->actingAs($inactive)->post(route('operations.backups.restore-test', $backup))->assertForbidden();
        $this->assertSame(0, BackupRun::query()->where('type', 'restore_test')->count());
    }
}