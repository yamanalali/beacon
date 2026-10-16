<?php

namespace Tests\Feature\Operations;

use App\Enums\UserRole;
use App\Exceptions\ThirdPartySharingNotApproved;
use App\Models\BackupRun;
use App\Models\User;
use App\Services\Operations\BackupService;
use App\Support\DataControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_only_technical_admin_reaches_security_and_backup_screens(): void
    {
        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::BeaconAdmin, UserRole::MfiUser] as $role) {
            $u = $this->user($role);
            $this->actingAs($u)->get(route('operations.security'))->assertForbidden();
            $this->actingAs($u)->get(route('operations.backups'))->assertForbidden();
            $this->actingAs($u)->post(route('operations.backups.run'))->assertForbidden();
        }
        $this->assertDatabaseCount('backup_runs', 0);

        $tech = $this->user(UserRole::TechnicalAdmin);
        $this->actingAs($tech)->get(route('operations.security'))->assertOk()
            ->assertInertia(fn ($p) => $p->component('Operations/Security')->has('items'));
        $this->actingAs($tech)->get(route('operations.backups'))->assertOk();
    }

    public function test_checklist_never_exposes_secrets(): void
    {
        config(['operations.backup.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $response = $this->actingAs($this->user(UserRole::TechnicalAdmin))->get(route('operations.security'));
        $body = json_encode($response->viewData('page'));
        $this->assertStringNotContainsString((string) config('app.key'), $body);
        $this->assertStringNotContainsString((string) config('operations.backup.key'), $body);
        $this->assertStringContainsString('pending_decision', $body);
    }

    public function test_data_control_register_visible_to_admins_only(): void
    {
        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::MfiUser] as $role) {
            $this->actingAs($this->user($role))->get(route('operations.data-control'))->assertForbidden();
        }
        $this->actingAs($this->user(UserRole::BeaconAdmin))->get(route('operations.data-control'))->assertOk();
        $this->actingAs($this->user(UserRole::TechnicalAdmin))->get(route('operations.data-control'))
            ->assertInertia(fn ($p) => $p->where('processors.0.approved', false));
    }

    public function test_third_party_sharing_blocked_until_approved_and_limited_to_allowed_fields(): void
    {
        $this->assertFalse(DataControl::isApproved('ocr'));
        try {
            DataControl::assertCanShare('ocr', ['receipt_image']);
            $this->fail('Unapproved processor allowed.');
        } catch (ThirdPartySharingNotApproved) {
        }

        config(['operations.data_processors.ocr.approved' => true, 'operations.data_processors.ocr.allowed_data' => ['receipt_image']]);
        DataControl::assertCanShare('ocr', ['receipt_image']);

        $this->expectException(ThirdPartySharingNotApproved::class);
        DataControl::assertCanShare('ocr', ['receipt_image', 'owner_name']);
    }

    public function test_unknown_processor_is_refused(): void
    {
        $this->expectException(ThirdPartySharingNotApproved::class);
        DataControl::assertCanShare('random_saas');
    }

    public function test_daily_backup_is_scheduled(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($e) => str_contains($e->command ?? '', 'beacon:backup') && ! str_contains($e->command, 'prune'));
        $this->assertCount(1, $events);
    }

    public function test_user_can_end_own_other_sessions_only(): void
    {
        config(['session.driver' => 'database']);
        $me = $this->user(UserRole::SmeUser);
        $other = $this->user(UserRole::SmeUser);
        foreach ([[$me->id, 'a'], [$me->id, 'b'], [$other->id, 'c']] as [$uid, $id]) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $uid, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        }

        $this->actingAs($me)->get(route('account.sessions'))->assertOk()
            ->assertInertia(fn ($p) => $p->component('Operations/Sessions')->has('sessions', 2));
        $this->actingAs($me)->delete(route('account.sessions.destroy-others'))->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'a']);
        $this->assertDatabaseHas('sessions', ['id' => 'c']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'security.sessions_revoked', 'actor_id' => $me->id]);
    }

    public function test_https_enforcement_redirects_and_sets_hsts(): void
    {
        config(['operations.force_https' => true]);
        $this->get('http://localhost/login')->assertRedirect('https://localhost/login');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security');
    }
}
