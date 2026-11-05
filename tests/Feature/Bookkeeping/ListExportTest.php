<?php

namespace Tests\Feature\Bookkeeping;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListExportTest extends TestCase
{
    use RefreshDatabase;

    private Enterprise $enterprise;

    private User $sme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enterprise = Enterprise::query()->create([
            'business_name' => 'منشأة التصدير',
            'location' => 'damascus',
            'sector' => 'تجارة',
        ]);
        $this->sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $this->enterprise->members()->attach($this->sme->id);
        FinancialTransaction::query()->forceCreate([
            'public_id' => (string) str()->ulid(),
            'enterprise_id' => $this->enterprise->id,
            'created_by' => $this->sme->id,
            'type' => 'sale',
            'transaction_date' => '2026-09-10',
            'amount' => '100.00',
            'currency' => 'SYP',
            'category' => 'general_sales',
            'payment_status' => 'paid',
            'status' => 'posted',
            'counterparty' => '=CMD',
        ]);
    }

    public function test_transaction_list_export_csv_xlsx_and_isolation(): void
    {
        $params = ['enterprise' => $this->enterprise, 'format' => 'csv'];

        $csv = $this->actingAs($this->sme)->get(route('enterprises.transactions.export', $params));
        $csv->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString('مبيعات عامة', $body);
        $this->assertStringContainsString("'=CMD", $body);

        $xlsx = $this->actingAs($this->sme)->get(route('enterprises.transactions.export', [
            'enterprise' => $this->enterprise, 'format' => 'xlsx',
        ]));
        $xlsx->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());

        $other = User::factory()->create(['role' => UserRole::SmeUser]);
        $this->actingAs($other)->get(route('enterprises.transactions.export', $params))->assertForbidden();

        $mfi = User::factory()->create(['role' => UserRole::MfiUser]);
        $this->actingAs($mfi)->get(route('enterprises.transactions.export', $params))->assertForbidden();

        $this->assertDatabaseHas('audit_logs', ['action' => 'transactions.list.exported']);
    }

    public function test_enterprise_directory_export_scoped_to_advisor(): void
    {
        $other = Enterprise::query()->create(['business_name' => 'أخرى', 'location' => 'daraa', 'sector' => 'خدمات']);
        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $this->enterprise->advisors()->attach($advisor->id);

        $csv = $this->actingAs($advisor)->get(route('enterprises.export', ['format' => 'csv']));
        $csv->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString('منشأة التصدير', $body);
        $this->assertStringNotContainsString('أخرى', $body);

        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $adminCsv = $this->actingAs($admin)->get(route('enterprises.export', ['format' => 'xlsx']));
        $adminCsv->assertOk();
        $this->assertStringStartsWith('PK', $adminCsv->streamedContent());

        $this->actingAs($this->sme)->get(route('enterprises.export', ['format' => 'csv']))->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'enterprises.list.exported']);
    }
}
