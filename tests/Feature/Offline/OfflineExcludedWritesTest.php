<?php

namespace Tests\Feature\Offline;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\Referral;
use App\Models\User;
use App\Support\OfflinePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\Readiness\ReadinessTestHelpers;
use Tests\TestCase;

/** Live-only writes must reject offline operation keys without side effects. */
class OfflineExcludedWritesTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReadiness();
    }

    private function offlineHeaders(User $user, string $key): array
    {
        return [
            'X-Beacon-Operation-Key' => $key,
            'X-Beacon-Offline-Scope' => OfflinePolicy::scope($user),
            'X-Beacon-Offline-Replay' => '1',
        ];
    }

    public function test_transaction_approve_rejects_offline_operation_key(): void
    {
        $enterprise = $this->enterprise();
        $sme = $this->smeOf($enterprise);
        $sme->forceFill(['access_permissions' => ['require_approval' => true]])->save();
        $admin = $this->user(UserRole::BeaconAdmin);

        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), [
            'type' => 'expense',
            'transaction_date' => now()->toDateString(),
            'amount' => '100',
            'currency' => 'SYP',
            'category' => 'rent',
            'payment_status' => 'paid',
        ])->assertRedirect();

        $transaction = FinancialTransaction::query()->sole();
        $this->assertSame('pending_approval', $transaction->status->value);

        $this->actingAs($admin)->postJson(
            route('transactions.approve', $transaction),
            [],
            $this->offlineHeaders($admin, (string) Str::uuid())
        )->assertStatus(422);

        $this->assertSame('pending_approval', $transaction->fresh()->status->value);
        $this->assertDatabaseCount('offline_operations', 0);
    }

    public function test_referral_consent_and_share_reject_offline_operation_key(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $sme = $this->smeOf($enterprise);
        $institution = $this->institution();

        $this->actingAs($advisor)->post(route('enterprises.referrals.store', $enterprise), [
            'mfi_institution_id' => $institution->id,
            'purpose_ar' => 'تمويل',
            'disclosed_items' => ['enterprise_profile'],
        ])->assertRedirect();

        $referral = Referral::query()->sole();

        $this->actingAs($sme)->postJson(
            route('referrals.consent', $referral),
            ['confirm' => true],
            $this->offlineHeaders($sme, (string) Str::uuid())
        )->assertStatus(422);

        $this->assertDatabaseCount('referral_consents', 0);

        $this->actingAs($sme)->post(route('referrals.consent', $referral), ['confirm' => true])->assertRedirect();

        $this->actingAs($advisor)->postJson(
            route('referrals.share', $referral),
            [],
            $this->offlineHeaders($advisor, (string) Str::uuid())
        )->assertStatus(422);

        $this->assertSame('draft', $referral->fresh()->status);
        $this->assertDatabaseCount('offline_operations', 0);
    }

    public function test_offline_writes_whitelist_excludes_security_sensitive_routes(): void
    {
        $forbidden = [
            'transactions.approve',
            'referrals.consent',
            'referrals.share',
            'referrals.consent.withdraw',
            'users.store',
            'account.password',
            'two-factor.enable',
            'operations.backups.run',
        ];

        foreach ($forbidden as $route) {
            $this->assertNotContains($route, OfflinePolicy::WRITES, $route.' must stay live-only');
        }

        $this->assertContains('enterprises.transactions.store', OfflinePolicy::WRITES);
        $this->assertContains('enterprises.update', OfflinePolicy::WRITES);
        $this->assertContains('enterprises.documents.store', OfflinePolicy::WRITES);
    }
}
