<?php

namespace Tests\Feature\Finance;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\User;
use App\Services\FinancialReports\FinancialReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    private Enterprise $enterprise;

    private User $sme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $this->sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $this->enterprise->members()->attach($this->sme->id);
    }

    private function record(array $data): FinancialTransaction
    {
        $this->actingAs($this->sme)->post(route('enterprises.transactions.store', $this->enterprise), array_merge([
            'transaction_date' => '2026-09-10', 'currency' => 'SYP', 'payment_status' => 'paid',
        ], $data))->assertSessionHasNoErrors();

        return FinancialTransaction::query()->latest('id')->first();
    }

    private function report(string $key, string $basis = 'accrual'): array
    {
        return app(FinancialReportService::class)->generate(
            $key, $this->enterprise, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-09-30'), $basis,
        )->toArray()['sections'];
    }

    private function seedScenario(): void
    {
        $this->record(['type' => 'sale', 'category' => 'general_sales', 'amount' => '1000']);
        $credit = $this->record(['type' => 'sale', 'category' => 'general_sales', 'amount' => '500', 'payment_status' => 'unpaid']);
        $this->actingAs($this->sme)->post(route('transactions.settlements.store', $credit), ['settlement_date' => '2026-09-20', 'amount' => '200']);
        $this->record(['type' => 'expense', 'category' => 'rent', 'amount' => '300']);
        $this->record(['type' => 'purchase', 'category' => 'goods', 'amount' => '400', 'payment_status' => 'unpaid']);
        $this->record(['type' => 'sale', 'category' => 'general_sales', 'amount' => '10', 'currency' => 'USD']);
    }

    public function test_income_statement_accrual_counts_credit_sale_once_and_separates_currencies(): void
    {
        $this->seedScenario();

        $rows = $this->report('income_statement')[0]['rows'];
        $this->assertSame([['SYP', '1500.00', '700.00', '800.00'], ['USD', '10.00', '0.00', '10.00']], $rows);
    }

    public function test_income_statement_cash_basis_uses_payments(): void
    {
        $this->seedScenario();

        $rows = $this->report('income_statement', 'cash')[0]['rows'];
        $this->assertSame(['SYP', '1200.00', '300.00', '900.00'], $rows[0]);
    }

    public function test_cash_flow_and_receivables_and_payables(): void
    {
        $this->seedScenario();

        $this->assertSame(['SYP', '1200.00', '300.00', '900.00'], $this->report('cash_flow')[0]['rows'][0]);
        $this->assertSame([['SYP', '300.00']], $this->report('receivables')[0]['rows']);
        $this->assertSame([['SYP', '400.00']], $this->report('payables')[0]['rows']);
    }

    public function test_sales_expense_and_monthly_reports(): void
    {
        $this->seedScenario();

        $sales = $this->report('sales')[0]['rows'];
        $this->assertContains(['SYP', 'بيع', 'مبيعات عامة', '2', '1500.00', '1200.00', '300.00'], $sales);

        $expenses = $this->report('expenses')[0]['rows'];
        $this->assertCount(2, $expenses);

        $monthly = $this->report('monthly')[0]['rows'];
        $this->assertContains(['2026-09', 'SYP', '1500.00', '700.00', '800.00', '1200.00', '300.00'], $monthly);
    }

    public function test_corrected_originals_are_excluded(): void
    {
        $t = $this->record(['type' => 'sale', 'category' => 'general_sales', 'amount' => '100']);
        $this->actingAs($this->sme)->post(route('transactions.correct', $t), [
            'type' => 'sale', 'category' => 'general_sales', 'amount' => '150', 'transaction_date' => '2026-09-10',
            'currency' => 'SYP', 'payment_status' => 'paid', 'correction_reason' => 'تصحيح المبلغ',
        ])->assertSessionHasNoErrors();

        $this->assertSame([['SYP', '150.00', '0.00', '150.00']], $this->report('income_statement')[0]['rows']);
        // Correcting the invoice does not invent the additional 50 in cash.
        $this->assertSame(['SYP', '100.00', '0.00', '100.00'], $this->report('cash_flow')[0]['rows'][0]);
        $this->assertSame([['SYP', '50.00']], $this->report('receivables')[0]['rows']);
    }

    public function test_exports_download_csv_and_xlsx_and_are_audited(): void
    {
        $this->seedScenario();
        $params = ['enterprise' => $this->enterprise, 'report' => 'sales', 'from' => '2026-09-01', 'to' => '2026-09-30'];

        $csv = $this->actingAs($this->sme)->get(route('enterprises.reports.export', $params + ['format' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('مبيعات عامة', $csv->streamedContent());

        $xlsx = $this->actingAs($this->sme)->get(route('enterprises.reports.export', $params + ['format' => 'xlsx']));
        $xlsx->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());

        $this->actingAs($this->sme)->get(route('enterprises.reports.export', $params + ['format' => 'exe']))->assertNotFound();
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported']);
    }

    public function test_formula_injection_is_neutralised_in_csv(): void
    {
        $this->record(['type' => 'sale', 'category' => 'general_sales', 'amount' => '5', 'payment_status' => 'unpaid', 'counterparty' => '=HYPERLINK("x")']);

        $csv = $this->actingAs($this->sme)->get(route('enterprises.reports.export', [
            'enterprise' => $this->enterprise, 'format' => 'csv', 'report' => 'receivables', 'to' => '2026-09-30', 'from' => '2026-09-01',
        ]))->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_role_permissions_for_report_page_and_exports(): void
    {
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $this->enterprise->advisors()->attach($advisor->id);
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);

        $allowed = [$this->sme, $advisor, $admin];
        $denied = [
            User::factory()->create(['role' => UserRole::SmeUser]),
            User::factory()->create(['role' => UserRole::BeaconAdvisor]),
            User::factory()->create(['role' => UserRole::MfiUser]),
            User::factory()->create(['role' => UserRole::TechnicalAdmin]),
            User::factory()->create(['role' => UserRole::BeaconAdmin, 'is_active' => false]),
        ];

        foreach ($allowed as $user) {
            $this->actingAs($user)->get(route('enterprises.reports.show', $this->enterprise))->assertOk();
            $this->actingAs($user)->get(route('enterprises.reports.export', ['enterprise' => $this->enterprise, 'format' => 'csv']))->assertOk();
        }

        foreach ($denied as $user) {
            $response = $this->actingAs($user)->get(route('enterprises.reports.show', $this->enterprise));
            $this->assertContains($response->status(), [302, 403], $user->role->value);
            $response = $this->actingAs($user)->get(route('enterprises.reports.export', ['enterprise' => $this->enterprise, 'format' => 'xlsx']));
            $this->assertContains($response->status(), [302, 403], $user->role->value);
        }
    }
}
