<?php

namespace Tests\Feature\Administration;

use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Models\Currency;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\ReadinessRuleSet;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\FinancialReports\FinancialReportService;
use App\Support\Currencies;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminControlsTest extends TestCase
{
    use RefreshDatabase;

    private function enterprise(): array
    {
        $enterprise = Enterprise::query()->create(['business_name' => 'Store', 'location' => 'damascus']);
        $user = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise->members()->attach($user);

        return [$enterprise, $user];
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::BeaconAdmin]);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['type' => 'sale', 'transaction_date' => now()->toDateString(), 'amount' => '100.00', 'currency' => 'SYP', 'category' => 'general_sales', 'payment_status' => 'paid'], $overrides);
    }

    public function test_admin_can_manage_currencies_and_default_but_cannot_disable_the_default(): void
    {
        $this->actingAs($this->admin())->post('/admin/currencies', ['code' => 'EUR', 'name' => 'Euro'])->assertSessionHasNoErrors();
        $this->put('/admin/currencies/EUR', ['name' => 'Euro', 'enabled' => true, 'is_default' => true])->assertSessionHasNoErrors();
        $this->assertSame('EUR', app(Currencies::class)->default());
        $this->put('/admin/currencies/EUR', ['name' => 'Euro', 'enabled' => false, 'is_default' => false])->assertSessionHasErrors('enabled');
        $this->assertTrue(Currency::findOrFail('EUR')->enabled);
        $this->put('/admin/currencies/USD', ['name' => 'US Dollar', 'enabled' => false, 'is_default' => false])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('USD', app(Currencies::class)->enabled());
    }

    public function test_currency_settings_are_admin_only_and_disabled_currency_blocks_new_transactions(): void
    {
        [$enterprise, $user] = $this->enterprise();
        $this->actingAs($user)->post('/admin/currencies', ['code' => 'EUR', 'name' => 'Euro'])->assertForbidden();
        Currency::findOrFail('USD')->update(['enabled' => false]);
        $this->post(route('enterprises.transactions.store', $enterprise), $this->payload(['currency' => 'USD']))->assertSessionHasErrors('currency');
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_disabled_currency_preserves_historical_corrections_and_payments(): void
    {
        [$enterprise, $user] = $this->enterprise();
        $this->actingAs($user)->post(route('enterprises.transactions.store', $enterprise), $this->payload(['currency' => 'USD', 'payment_status' => 'unpaid']))->assertSessionHasNoErrors();
        $transaction = FinancialTransaction::query()->sole();
        Currency::findOrFail('USD')->update(['enabled' => false]);
        $this->get(route('transactions.correct.edit', $transaction))->assertOk();
        $this->post(route('transactions.correct', $transaction), $this->payload(['currency' => 'USD', 'payment_status' => 'unpaid', 'amount' => '120', 'correction_reason' => 'Fix amount']))->assertSessionHasNoErrors();
        $corrected = FinancialTransaction::query()->latest('id')->first();
        $this->post(route('transactions.settlements.store', $corrected), ['settlement_date' => now()->toDateString(), 'amount' => '20'])->assertSessionHasNoErrors();
        $this->assertSame('20.00', $corrected->fresh()->settlements()->sole()->amount);
    }

    public function test_permissions_can_be_customized_reset_and_cannot_be_self_edited(): void
    {
        [$enterprise, $user] = $this->enterprise();
        $admin = $this->admin();
        $permissions = ['view' => false, 'record' => false, 'approve' => false, 'export' => false, 'require_approval' => true];
        $this->actingAs($admin)->put("/users/{$user->id}/access", ['permissions' => $permissions])->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->can('view', $enterprise));
        $this->put("/users/{$admin->id}/access", ['permissions' => $permissions])->assertForbidden();
        $this->put("/users/{$user->id}/access", ['permissions' => ['view' => true]])->assertSessionHasErrors('permissions.record');
        $this->put("/users/{$user->id}/access", ['permissions' => null])->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->can('create', [FinancialTransaction::class, $enterprise]));
        $this->assertNull($user->fresh()->access_permissions);
    }

    public function test_record_permission_never_expands_enterprise_assignments_or_restricted_roles(): void
    {
        [$enterprise] = $this->enterprise();
        [$unrelated] = $this->enterprise();
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor, 'access_permissions' => ['record' => true]]);
        $enterprise->advisors()->attach($advisor);
        $this->actingAs($advisor)->post(route('enterprises.transactions.store', $enterprise), $this->payload())->assertSessionHasNoErrors();
        $this->post(route('enterprises.transactions.store', $unrelated), $this->payload())->assertForbidden();
        foreach ([UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $restricted = User::factory()->create(['role' => $role, 'access_permissions' => ['view' => true, 'record' => true, 'approve' => true]]);
            $this->actingAs($restricted)->post(route('enterprises.transactions.store', $enterprise), $this->payload())->assertForbidden();
        }
    }

    public function test_view_and_export_revocations_are_enforced_on_direct_urls(): void
    {
        [$enterprise, $user] = $this->enterprise();
        $user->forceFill(['access_permissions' => ['export' => false]])->save();
        $this->actingAs($user)->get("/enterprises/{$enterprise->public_id}/reports/export/csv")->assertForbidden();
        $user->forceFill(['access_permissions' => ['view' => false]])->save();
        $this->actingAs($user->fresh())->get(route('enterprises.transactions.index', $enterprise))->assertForbidden();
        $admin = $this->admin();
        $admin->forceFill(['access_permissions' => ['export' => false]])->save();
        $this->actingAs($admin)->get('/admin/audit-logs/export')->assertForbidden();
    }

    public function test_pending_transactions_require_another_authorized_user_and_count_only_after_approval(): void
    {
        [$enterprise, $user] = $this->enterprise();
        $user->forceFill(['access_permissions' => ['require_approval' => true, 'approve' => true]])->save();
        $this->actingAs($user)->post(route('enterprises.transactions.store', $enterprise), $this->payload())->assertSessionHasNoErrors();
        $transaction = FinancialTransaction::query()->sole();
        $this->assertSame(TransactionStatus::PendingApproval, $transaction->status);
        $this->assertSame(0, FinancialTransaction::query()->posted()->count());
        $report = fn () => app(FinancialReportService::class)->generate('cash_flow', $enterprise, CarbonImmutable::today()->subDay(), CarbonImmutable::today())->toArray()['sections'][0]['rows'];
        $this->assertSame([], $report());
        $this->post("/transactions/{$transaction->public_id}/approve")->assertForbidden();
        $this->post(route('transactions.correct', $transaction), $this->payload(['correction_reason' => 'Change']))->assertForbidden();
        $this->post(route('transactions.settlements.store', $transaction), ['settlement_date' => now()->toDateString(), 'amount' => '5'])->assertForbidden();
        $outsider = User::factory()->create(['role' => UserRole::BeaconAdvisor, 'access_permissions' => ['approve' => true]]);
        $this->actingAs($outsider)->post("/transactions/{$transaction->public_id}/approve")->assertForbidden();
        $admin = $this->admin();
        $this->actingAs($admin)->post("/transactions/{$transaction->public_id}/approve")->assertSessionHasNoErrors();
        $this->assertSame(1, FinancialTransaction::query()->posted()->count());
        $this->assertSame([['SYP', '100.00', '0.00', '100.00']], $report());
        $this->assertSame($admin->id, $transaction->fresh()->approved_by);
        $this->assertNotNull($transaction->fresh()->approved_at);
        $this->post("/transactions/{$transaction->public_id}/approve")->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'transaction.approved']);
        $this->assertFalse($user->fresh()->can('correct', $transaction->fresh()));
    }

    public function test_replayed_offline_submissions_are_idempotent_and_changed_payload_is_rejected(): void
    {
        [$enterprise, $user] = $this->enterprise();
        $payload = $this->payload(['client_draft_id' => (string) Str::uuid()]);
        $this->actingAs($user)->post(route('enterprises.transactions.store', $enterprise), $payload)->assertSessionHasNoErrors();
        $first = FinancialTransaction::query()->sole();
        $this->post(route('enterprises.transactions.store', $enterprise), $payload)->assertRedirect(route('transactions.show', $first));
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertDatabaseCount('transaction_settlements', 1);
        $this->post(route('enterprises.transactions.store', $enterprise), array_replace($payload, ['amount' => '200']))->assertSessionHasErrors('client_draft_id');
        $this->assertDatabaseCount('financial_transactions', 1);
        [$other, $otherUser] = $this->enterprise();
        $this->actingAs($otherUser)->post(route('enterprises.transactions.store', $other), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('financial_transactions', 2);
    }

    public function test_admin_creates_hashed_single_use_invitation_and_guest_accepts_into_assigned_enterprise(): void
    {
        [$enterprise] = $this->enterprise();
        $this->actingAs($this->admin())->post('/admin/invitations', ['name' => 'Invited', 'email' => 'NEW@example.com', 'role' => 'sme_user', 'enterprise_id' => $enterprise->id])->assertSessionHasNoErrors()->assertSessionHas('invitation_link');
        $link = session('invitation_link');
        $token = basename($link);
        $invite = UserInvitation::query()->sole();
        $this->assertSame(hash('sha256', $token), $invite->token_hash);
        $this->assertSame('new@example.com', $invite->email);
        $this->assertArrayNotHasKey('token_hash', $invite->toArray());
        auth()->logout();
        $this->get($link)->assertOk();
        $this->post($link, ['password' => 'Secure-example-123', 'password_confirmation' => 'Secure-example-123'])->assertRedirect(route('login'));
        $user = User::query()->where('email', 'new@example.com')->sole();
        $this->assertTrue(Hash::check('Secure-example-123', $user->password));
        $this->assertTrue($enterprise->members()->whereKey($user)->exists());
        $this->assertNotNull($invite->fresh()->accepted_at);
        $this->get($link)->assertGone();
    }

    public function test_invitation_expiry_revocation_and_issuer_deactivation_block_acceptance(): void
    {
        $admin = $this->admin();
        foreach (['expired', 'revoked', 'inactive'] as $case) {
            $token = Str::random(64);
            UserInvitation::query()->create(['email' => "$case@example.com", 'name' => 'Invite', 'role' => 'beacon_advisor', 'token_hash' => hash('sha256', $token), 'invited_by' => $admin->id, 'expires_at' => $case === 'expired' ? now()->subDay() : now()->addDay(), 'revoked_at' => $case === 'revoked' ? now() : null]);
            if ($case === 'inactive') {
                $admin->forceFill(['is_active' => false])->save();
            }
            $this->get("/invitations/$token")->assertGone();
            $this->post("/invitations/$token", ['password' => 'Secure-example-123', 'password_confirmation' => 'Secure-example-123'])->assertGone();
        }
        $this->assertDatabaseCount('users', 1);
    }

    public function test_criteria_library_is_admin_only_and_preserves_existing_questions(): void
    {
        $set = ReadinessRuleSet::query()->create(['name_ar' => 'Existing', 'version_number' => 1, 'status' => 'draft']);
        $criterion = $set->criteria()->create(['code' => 'sample', 'label_ar' => 'Question', 'weight' => 2, 'type' => 'boolean', 'config' => ['yes_points' => 1, 'no_points' => 0], 'missing_data_treatment' => 'needs_review', 'is_mandatory' => false]);
        $user = User::factory()->create(['role' => UserRole::SmeUser]);
        $this->actingAs($user)->getJson('/admin/readiness/criteria-library')->assertForbidden();
        $this->actingAs($this->admin())->getJson('/admin/readiness/criteria-library')->assertOk()->assertJsonPath('criteria.0.id', $criterion->id)->assertJsonPath('criteria.0.rule_set.name_ar', 'Existing');
        $this->assertDatabaseCount('readiness_criteria', 1);
    }
}
