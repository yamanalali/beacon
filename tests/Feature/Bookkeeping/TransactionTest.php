<?php

namespace Tests\Feature\Bookkeeping;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionTest extends TestCase
{
    use RefreshDatabase;

    private function enterpriseWithSme(): array
    {
        $enterprise = Enterprise::query()->create(['business_name' => 'منشأة اختبار', 'location' => 'damascus']);
        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise->members()->attach($sme->id);

        return [$enterprise, $sme];
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'sale',
            'transaction_date' => '2026-10-01',
            'amount' => '1000.50',
            'currency' => 'SYP',
            'category' => 'general_sales',
            'payment_status' => 'paid',
            'counterparty' => 'زبون',
            'description' => 'بيع بضاعة',
        ], $overrides);
    }

    public function test_sme_can_record_paid_sale_with_exact_decimal_amount(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();

        $this->actingAs($sme)
            ->post(route('enterprises.transactions.store', $enterprise), $this->payload())
            ->assertRedirect();

        $t = FinancialTransaction::query()->with('settlements')->sole();
        $this->assertSame('1000.50', (string) $t->amount);
        $this->assertSame('paid', $t->payment_status->value);
        $this->assertSame(0, $t->outstandingMinor());
        $this->assertCount(1, $t->settlements);
        $this->assertDatabaseHas('audit_logs', ['action' => 'transaction.created']);
    }

    public function test_category_must_match_type_and_currency_must_be_configured(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();

        $this->actingAs($sme)
            ->post(route('enterprises.transactions.store', $enterprise), $this->payload(['category' => 'rent', 'currency' => 'EUR']))
            ->assertSessionHasErrors(['category', 'currency']);

        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_credit_sale_then_payment_does_not_double_count_revenue(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();

        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), $this->payload([
            'amount' => '500', 'payment_status' => 'unpaid',
        ]));
        $sale = FinancialTransaction::query()->sole();
        $this->assertSame(50000, $sale->load('settlements')->outstandingMinor());

        $this->actingAs($sme)->post(route('transactions.settlements.store', $sale), [
            'settlement_date' => '2026-10-05', 'amount' => '200',
        ])->assertSessionHasNoErrors();
        $this->assertSame('partially_paid', $sale->fresh()->payment_status->value);

        $this->actingAs($sme)->post(route('transactions.settlements.store', $sale), [
            'settlement_date' => '2026-10-06', 'amount' => '300',
        ])->assertSessionHasNoErrors();

        // Still exactly one revenue record of 500; payments are settlements only.
        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertSame('500.00', (string) $sale->fresh()->amount);
        $this->assertSame('paid', $sale->fresh()->payment_status->value);
        $this->assertSame(0, $sale->fresh()->load('settlements')->outstandingMinor());
    }

    public function test_credit_purchase_then_supplier_payment_does_not_double_count_expense(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();

        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), $this->payload([
            'type' => 'purchase', 'category' => 'goods', 'amount' => '750', 'payment_status' => 'unpaid',
        ]));
        $purchase = FinancialTransaction::query()->sole();

        $this->actingAs($sme)->post(route('transactions.settlements.store', $purchase), [
            'settlement_date' => '2026-10-07', 'amount' => '750',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertSame('paid', $purchase->fresh()->payment_status->value);
    }

    public function test_settlement_cannot_exceed_outstanding_amount(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();
        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), $this->payload([
            'amount' => '100', 'payment_status' => 'partially_paid', 'paid_amount' => '40',
        ]));
        $t = FinancialTransaction::query()->sole();

        $this->actingAs($sme)->post(route('transactions.settlements.store', $t), [
            'settlement_date' => '2026-10-07', 'amount' => '60.01',
        ])->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('transaction_settlements', 1);
    }

    public function test_partial_payment_requires_amount_below_total(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();

        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), $this->payload([
            'amount' => '100', 'payment_status' => 'partially_paid', 'paid_amount' => '100',
        ]))->assertSessionHasErrors('paid_amount');

        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_correction_preserves_original_and_records_reason(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();
        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), $this->payload(['amount' => '100']));
        $original = FinancialTransaction::query()->sole();

        $this->actingAs($sme)->post(route('transactions.correct', $original), $this->payload([
            'amount' => '120', 'correction_reason' => 'خطأ في كتابة المبلغ',
        ]))->assertRedirect();

        $original->refresh();
        $this->assertSame('superseded', $original->status->value);
        $this->assertSame('100.00', (string) $original->amount);

        $corrected = FinancialTransaction::query()->where('corrects_transaction_id', $original->id)->sole();
        $this->assertSame('120.00', (string) $corrected->amount);
        $this->assertSame('خطأ في كتابة المبلغ', $corrected->correction_reason);
        $this->assertDatabaseHas('audit_logs', ['action' => 'transaction.corrected']);

        // A superseded record cannot be corrected or settled again.
        $this->actingAs($sme)->post(route('transactions.correct', $original), $this->payload([
            'correction_reason' => 'مرة ثانية',
        ]))->assertForbidden();
    }

    public function test_correction_requires_reason(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();
        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), $this->payload());
        $original = FinancialTransaction::query()->sole();

        $this->actingAs($sme)->post(route('transactions.correct', $original), $this->payload())
            ->assertSessionHasErrors('correction_reason');
        $this->assertSame('posted', $original->fresh()->status->value);
    }

    public function test_other_sme_cannot_view_or_create_transactions(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();
        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), $this->payload());
        $t = FinancialTransaction::query()->sole();

        $other = User::factory()->create(['role' => UserRole::SmeUser]);

        $this->actingAs($other)->get(route('enterprises.transactions.index', $enterprise))->assertForbidden();
        $this->actingAs($other)->get(route('transactions.show', $t))->assertForbidden();
        $this->actingAs($other)->post(route('enterprises.transactions.store', $enterprise), $this->payload())->assertForbidden();
        $this->actingAs($other)->post(route('transactions.settlements.store', $t), ['settlement_date' => '2026-10-01', 'amount' => '1'])->assertForbidden();
    }

    public function test_mfi_and_technical_admin_and_unassigned_advisor_are_denied(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();
        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), $this->payload());
        $t = FinancialTransaction::query()->sole();

        foreach ([UserRole::MfiUser, UserRole::TechnicalAdmin, UserRole::BeaconAdvisor] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('enterprises.transactions.index', $enterprise))->assertForbidden();
            $this->actingAs($user)->get(route('transactions.show', $t))->assertForbidden();
        }
    }

    public function test_assigned_advisor_can_view_but_not_record(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $enterprise->advisors()->attach($advisor->id);

        $this->actingAs($advisor)->get(route('enterprises.transactions.index', $enterprise))->assertOk();
        $this->actingAs($advisor)->post(route('enterprises.transactions.store', $enterprise), $this->payload())->assertForbidden();
    }

    public function test_filters_by_type_and_payment_status(): void
    {
        [$enterprise, $sme] = $this->enterpriseWithSme();
        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), $this->payload());
        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), $this->payload([
            'type' => 'expense', 'category' => 'rent', 'payment_status' => 'unpaid',
        ]));

        $this->actingAs($sme)
            ->get(route('enterprises.transactions.index', ['enterprise' => $enterprise, 'type' => 'expense']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Transactions/Index')->has('transactions.data', 1)
                ->where('transactions.data.0.type', 'expense'));

        $this->actingAs($sme)
            ->get(route('enterprises.transactions.index', ['enterprise' => $enterprise, 'payment_status' => 'paid']))
            ->assertInertia(fn ($page) => $page->has('transactions.data', 1)->where('transactions.data.0.type', 'sale'));
    }
}
