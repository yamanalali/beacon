<?php

namespace Tests\Feature\Bookkeeping;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\TransactionSettlement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoidSettlementTest extends TestCase
{
    use RefreshDatabase;

    private User $sme;

    private Enterprise $enterprise;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enterprise = Enterprise::query()->create(['business_name' => 'منشأة اختبار', 'location' => 'damascus']);
        $this->sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $this->enterprise->members()->attach($this->sme->id);
    }

    /** Credit sale of 500 with a 200 and a 300 payment. */
    private function paidSale(): array
    {
        $this->actingAs($this->sme)->post(route('enterprises.transactions.store', $this->enterprise), [
            'type' => 'sale', 'transaction_date' => '2026-10-01', 'amount' => '500', 'currency' => 'SYP',
            'category' => 'general_sales', 'payment_status' => 'unpaid',
        ])->assertSessionHasNoErrors();
        $sale = FinancialTransaction::query()->sole();

        foreach (['200', '300'] as $i => $amount) {
            $this->post(route('transactions.settlements.store', $sale), [
                'settlement_date' => '2026-10-0'.($i + 2), 'amount' => $amount,
            ])->assertSessionHasNoErrors();
        }
        $settlements = TransactionSettlement::query()->orderBy('id')->get();
        $this->assertSame('paid', $sale->fresh()->payment_status->value);

        return [$sale, $settlements[0], $settlements[1]];
    }

    public function test_void_marks_settlement_recalculates_status_and_audits(): void
    {
        [$sale, $first, $second] = $this->paidSale();

        $this->actingAs($this->sme)
            ->post(route('transactions.settlements.void', [$sale, $second]), ['reason' => 'دفعة مسجلة بالخطأ'])
            ->assertRedirect(route('transactions.show', $sale))
            ->assertSessionHas('success');

        $second->refresh();
        $this->assertNotNull($second->voided_at);
        $this->assertSame($this->sme->id, $second->voided_by);
        $this->assertSame('دفعة مسجلة بالخطأ', $second->void_reason);

        $sale = $sale->fresh()->load('settlements');
        $this->assertSame('partially_paid', $sale->payment_status->value);
        $this->assertSame(30000, $sale->outstandingMinor());
        $this->assertDatabaseHas('audit_logs', ['action' => 'transaction.settlement_voided', 'target_id' => (string) $sale->id]);

        // Voiding the remaining payment returns the sale to unpaid; the record itself is never deleted.
        $this->post(route('transactions.settlements.void', [$sale, $first]))->assertSessionHasNoErrors();
        $this->assertSame('unpaid', $sale->fresh()->payment_status->value);
        $this->assertDatabaseCount('transaction_settlements', 2);
        $this->assertSame(50000, $sale->fresh()->load('settlements')->outstandingMinor());
        $this->assertDatabaseCount('financial_transactions', 1);
    }

    public function test_voided_payment_can_be_recorded_again_and_page_exposes_state(): void
    {
        [$sale, , $second] = $this->paidSale();

        $this->actingAs($this->sme)->post(route('transactions.settlements.void', [$sale, $second]));
        $this->post(route('transactions.settlements.store', $sale), ['settlement_date' => '2026-10-05', 'amount' => '300'])
            ->assertSessionHasNoErrors();
        $this->assertSame('paid', $sale->fresh()->payment_status->value);

        $this->get(route('transactions.show', $sale))->assertInertia(fn ($page) => $page
            ->component('Transactions/Show')
            ->where('can_void_settlement', true)
            ->has('settlements', 3)
            ->where('settlements.1.is_voided', true)
            ->where('settlements.0.is_voided', false));
    }

    public function test_second_void_fails_and_changes_nothing(): void
    {
        [$sale, , $second] = $this->paidSale();

        $this->actingAs($this->sme)->post(route('transactions.settlements.void', [$sale, $second]))->assertSessionHasNoErrors();
        $voidedAt = $second->fresh()->voided_at;

        $this->post(route('transactions.settlements.void', [$sale, $second]))->assertStatus(409);

        $this->assertEquals($voidedAt, $second->fresh()->voided_at);
        $this->assertSame(1, \App\Models\AuditLog::query()->where('action', 'transaction.settlement_voided')->count());
    }

    public function test_unauthorized_users_cannot_void(): void
    {
        [$sale, , $second] = $this->paidSale();

        $otherSme = User::factory()->create(['role' => UserRole::SmeUser]);
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $mfi = User::factory()->create(['role' => UserRole::MfiUser]);

        foreach ([$otherSme, $advisor, $mfi] as $user) {
            $this->actingAs($user)->post(route('transactions.settlements.void', [$sale, $second]))->assertForbidden();
        }
        auth()->logout();
        $this->post(route('transactions.settlements.void', [$sale, $second]))->assertRedirect();

        $this->assertNull($second->fresh()->voided_at);
        $this->assertSame('paid', $sale->fresh()->payment_status->value);
    }

    public function test_settlement_of_another_transaction_cannot_be_voided_through_this_one(): void
    {
        [$sale] = $this->paidSale();
        $this->post(route('enterprises.transactions.store', $this->enterprise), [
            'type' => 'sale', 'transaction_date' => '2026-10-01', 'amount' => '100', 'currency' => 'SYP',
            'category' => 'general_sales', 'payment_status' => 'paid',
        ])->assertSessionHasNoErrors();
        $other = FinancialTransaction::query()->where('id', '!=', $sale->id)->sole();
        $foreign = TransactionSettlement::query()->where('financial_transaction_id', $other->id)->sole();

        $this->post(route('transactions.settlements.void', [$sale, $foreign]))->assertNotFound();

        $this->assertNull($foreign->fresh()->voided_at);
        $this->assertSame('paid', $other->fresh()->payment_status->value);
    }

    public function test_cannot_void_after_the_transaction_is_corrected(): void
    {
        [$sale, , $second] = $this->paidSale();

        $this->actingAs($this->sme)->post(route('transactions.correct', $sale), [
            'type' => 'sale', 'transaction_date' => '2026-10-01', 'amount' => '500', 'currency' => 'SYP',
            'category' => 'general_sales', 'correction_reason' => 'تصحيح تجريبي',
        ])->assertSessionHasNoErrors();

        $this->post(route('transactions.settlements.void', [$sale, $second]))->assertForbidden();
        $this->assertNull($second->fresh()->voided_at);
    }

    public function test_voided_payment_is_excluded_from_cash_flow_report(): void
    {
        [$sale, , $second] = $this->paidSale();
        $this->actingAs($this->sme)->post(route('transactions.settlements.void', [$sale, $second]));

        $report = app(\App\Services\FinancialReports\FinancialReportService::class)->generate(
            'cash_flow',
            $this->enterprise,
            \Carbon\CarbonImmutable::parse('2026-10-01'),
            \Carbon\CarbonImmutable::parse('2026-10-31'),
        );

        $json = json_encode($report->toArray(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('200.00', $json);
        $this->assertStringNotContainsString('500.00', $json);
    }
}
