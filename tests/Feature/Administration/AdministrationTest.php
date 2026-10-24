<?php

namespace Tests\Feature\Administration;

use App\Actions\Audit\RecordAuditLog;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, User> */
    private function usersByRole(): array
    {
        $users = [];
        foreach (UserRole::cases() as $role) {
            $users[$role->value] = User::factory()->create(['role' => $role]);
        }

        return $users;
    }

    public function test_only_active_beacon_admin_reaches_admin_area(): void
    {
        $users = $this->usersByRole();
        $log = app(RecordAuditLog::class)->record($users['beacon_admin'], 'test.action', $users['sme_user']);

        $urls = ['/admin', '/admin/audit-logs', '/admin/audit-logs/'.$log->id, '/admin/audit-logs/export'];

        foreach ($urls as $url) {
            $this->actingAs($users['beacon_admin'])->get($url)->assertOk();
            foreach (['sme_user', 'beacon_advisor', 'mfi_user', 'technical_admin'] as $role) {
                $this->actingAs($users[$role])->get($url)->assertForbidden();
            }
        }

        $inactive = User::factory()->create(['role' => UserRole::BeaconAdmin, 'is_active' => false]);
        $this->actingAs($inactive)->get('/admin')->assertStatus(403);
        auth()->logout();
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_audit_log_filters_and_export_is_itself_audited(): void
    {
        $users = $this->usersByRole();
        $audit = app(RecordAuditLog::class);
        $audit->record($users['beacon_admin'], 'alpha.done', $users['sme_user']);
        $audit->record($users['beacon_admin'], 'beta.done', $users['sme_user']);

        $this->actingAs($users['beacon_admin'])->get('/admin/audit-logs?action=alpha.done')
            ->assertInertia(fn ($page) => $page->component('Administration/AuditLogs/Index')->has('logs.data', 1)->where('logs.data.0.action', 'alpha.done'));

        $csv = $this->actingAs($users['beacon_admin'])->get('/admin/audit-logs/export?action=beta.done');
        $csv->assertOk();
        $this->assertStringContainsString('beta.done', $csv->streamedContent());
        $this->assertDatabaseHas('audit_logs', ['action' => 'audit_log.exported']);
    }

    public function test_audit_details_and_export_are_readable_without_raw_json(): void
    {
        $users = $this->usersByRole();
        $log = app(RecordAuditLog::class)->record($users['beacon_admin'], 'user.role_changed', $users['sme_user'], [
            'before' => ['role' => 'sme_user'],
            'after' => ['role' => 'beacon_advisor', 'is_active' => true],
            'nested' => ['password' => 'not-visible'],
        ]);

        $this->actingAs($users['beacon_admin'])->get('/admin/audit-logs/'.$log->id)
            ->assertOk()->assertInertia(fn ($page) => $page
            ->missing('log.context_json')
            ->where('log.context_rows.0.label', 'قبل التغيير / الدور')
            ->where('log.context_rows.0.value', 'مستخدم المنشأة')
            ->where('log.context_rows.1.value', 'مستشار بيكون')
            ->where('log.context_rows.2.value', 'نعم'));
        $csv = $this->get('/admin/audit-logs/export?action=user.role_changed')->streamedContent();
        $this->assertStringContainsString('قبل التغيير / الدور: مستخدم المنشأة', $csv);
        $this->assertStringNotContainsString('"before":', $csv);
        $this->assertStringNotContainsString('not-visible', $csv);
        $this->assertSame('sme_user', $log->fresh()->context['before']['role']);
    }

    public function test_audit_history_cannot_be_modified_or_deleted(): void
    {
        $users = $this->usersByRole();
        $log = app(RecordAuditLog::class)->record($users['beacon_admin'], 'immutable.check', $users['sme_user']);

        try {
            $log->update(['action' => 'changed']);
            $this->fail('update should throw');
        } catch (LogicException) {
        }

        try {
            $log->delete();
            $this->fail('delete should throw');
        } catch (LogicException) {
        }

        foreach ([
            fn () => DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'tampered']),
            fn () => DB::table('audit_logs')->where('id', $log->id)->delete(),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('database trigger should block');
            } catch (QueryException) {
            }
        }

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id, 'action' => 'immutable.check']);
    }

    public function test_no_routes_allow_editing_audit_logs(): void
    {
        $users = $this->usersByRole();
        $log = app(RecordAuditLog::class)->record($users['beacon_admin'], 'x', $users['sme_user']);

        $this->actingAs($users['beacon_admin'])->delete('/admin/audit-logs/'.$log->id)->assertStatus(405);
        $this->actingAs($users['beacon_admin'])->put('/admin/audit-logs/'.$log->id, ['action' => 'y'])->assertStatus(405);
    }

    public function test_secrets_are_never_stored_in_audit_context(): void
    {
        $users = $this->usersByRole();
        $log = app(RecordAuditLog::class)->record($users['beacon_admin'], 'secret.check', $users['sme_user'], [
            'password' => 'p', 'nested' => ['api_key' => 'k', 'token' => 't', 'safe' => 'ok'], 'remember_token' => 'r',
        ]);

        $this->assertSame(['nested' => ['safe' => 'ok']], $log->fresh()->context);
    }

    public function test_admin_changes_role_with_before_after_audit_and_session_revocation(): void
    {
        $users = $this->usersByRole();
        $target = $users['sme_user'];
        DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $target->id, 'payload' => 'x', 'last_activity' => time()]);

        $this->actingAs($users['beacon_admin'])->put(route('users.role.update', $target), ['role' => 'beacon_advisor'])->assertRedirect();

        $this->assertSame(UserRole::BeaconAdvisor, $target->fresh()->role);
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $log = AuditLog::query()->where('action', 'user.role_changed')->sole();
        $this->assertSame(['before' => ['role' => 'sme_user'], 'after' => ['role' => 'beacon_advisor']], $log->context);
    }

    public function test_role_change_is_forbidden_for_non_admins_and_for_self(): void
    {
        $users = $this->usersByRole();
        $target = User::factory()->create(['role' => UserRole::SmeUser]);

        foreach (['sme_user', 'beacon_advisor', 'mfi_user', 'technical_admin'] as $role) {
            $this->actingAs($users[$role])->put(route('users.role.update', $target), ['role' => 'beacon_admin'])->assertForbidden();
        }

        $this->actingAs($users['beacon_admin'])->put(route('users.role.update', $users['beacon_admin']), ['role' => 'sme_user'])->assertForbidden();
        $this->actingAs($users['beacon_admin'])->put(route('users.role.update', $target), ['role' => 'super_user'])->assertSessionHasErrors('role');

        $this->assertSame(UserRole::SmeUser, $target->fresh()->role);
        $this->assertSame(UserRole::BeaconAdmin, $users['beacon_admin']->fresh()->role);
    }
}
