<?php

namespace Tests\Feature\Bookkeeping;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionApprovalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function pendingTransaction(): array
    {
        $enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $sme = User::factory()->create([
            'role' => UserRole::SmeUser,
            'access_permissions' => ['require_approval' => true],
        ]);
        $enterprise->members()->attach($sme->id);

        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), [
            'type' => 'expense',
            'transaction_date' => '2026-10-01',
            'amount' => '250',
            'currency' => 'SYP',
            'category' => 'rent',
            'payment_status' => 'paid',
        ])->assertRedirect();

        $transaction = FinancialTransaction::query()->sole();
        $this->assertSame('pending_approval', $transaction->status->value);

        return [$enterprise, $sme, $transaction];
    }

    public function test_assigned_advisor_with_approve_permission_can_approve_peer_transaction(): void
    {
        [$enterprise, $sme, $transaction] = $this->pendingTransaction();
        $advisor = User::factory()->create([
            'role' => UserRole::BeaconAdvisor,
            'access_permissions' => ['approve' => true],
        ]);
        $enterprise->advisors()->attach($advisor->id);

        $this->actingAs($advisor)->post(route('transactions.approve', $transaction))->assertRedirect();

        $transaction->refresh();
        $this->assertSame('posted', $transaction->status->value);
        $this->assertSame($advisor->id, (int) $transaction->approved_by);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transaction.approved',
            'actor_id' => $advisor->id,
        ]);
    }

    public function test_creator_cannot_approve_own_pending_transaction_even_with_approve_flag(): void
    {
        $enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $sme = User::factory()->create([
            'role' => UserRole::SmeUser,
            'access_permissions' => [
                'require_approval' => true,
                'approve' => true,
            ],
        ]);
        $enterprise->members()->attach($sme->id);

        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), [
            'type' => 'expense',
            'transaction_date' => '2026-10-01',
            'amount' => '250',
            'currency' => 'SYP',
            'category' => 'rent',
            'payment_status' => 'paid',
        ])->assertRedirect();

        $transaction = FinancialTransaction::query()->sole();
        $this->assertSame('pending_approval', $transaction->status->value);

        $this->actingAs($sme)->post(route('transactions.approve', $transaction))->assertForbidden();
        $this->assertSame('pending_approval', $transaction->fresh()->status->value);
    }

    public function test_unassigned_advisor_with_approve_permission_is_forbidden(): void
    {
        [, , $transaction] = $this->pendingTransaction();
        $outsider = User::factory()->create([
            'role' => UserRole::BeaconAdvisor,
            'access_permissions' => ['approve' => true],
        ]);

        $this->actingAs($outsider)->post(route('transactions.approve', $transaction))->assertForbidden();
        $this->assertSame('pending_approval', $transaction->fresh()->status->value);
    }

    public function test_settlement_on_pending_approval_transaction_is_rejected(): void
    {
        $enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $sme = User::factory()->create([
            'role' => UserRole::SmeUser,
            'access_permissions' => ['require_approval' => true],
        ]);
        $enterprise->members()->attach($sme->id);

        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), [
            'type' => 'expense',
            'transaction_date' => '2026-10-01',
            'amount' => '250',
            'currency' => 'SYP',
            'category' => 'rent',
            'payment_status' => 'unpaid',
        ])->assertRedirect();

        $transaction = FinancialTransaction::query()->sole();
        $this->assertSame('pending_approval', $transaction->status->value);
        $this->assertDatabaseCount('transaction_settlements', 0);

        $this->actingAs($sme)->post(route('transactions.settlements.store', $transaction), [
            'amount' => '50',
            'settlement_date' => '2026-10-02',
        ])->assertForbidden();

        $this->assertDatabaseCount('transaction_settlements', 0);
    }
}
