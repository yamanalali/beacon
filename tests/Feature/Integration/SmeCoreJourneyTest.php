<?php

namespace Tests\Feature\Integration;

use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\ReadinessAssessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Readiness\ReadinessTestHelpers;
use Tests\TestCase;

/**
 * End-to-end SME journey across profile, bookkeeping, documents, readiness, and dashboard.
 */
class SmeCoreJourneyTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReadiness();
        Storage::fake('local');
        $this->withoutVite();
    }

    public function test_sme_can_complete_core_daily_workflow_end_to_end(): void
    {
        $admin = $this->user(UserRole::BeaconAdmin);
        $advisor = $this->user(UserRole::BeaconAdvisor);

        $this->actingAs($admin)->post(route('enterprises.store'), [
            'business_name' => 'ورشة النور',
            'location' => 'damascus',
            'contact_phone' => '0944000001',
        ])->assertRedirect();

        $enterprise = Enterprise::query()->sole();
        $this->actingAs($admin)->post(route('enterprises.advisors.store', $enterprise), [
            'user_id' => $advisor->id,
        ])->assertRedirect();

        $sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $this->actingAs($admin)->post(route('enterprises.members.store', $enterprise), [
            'user_id' => $sme->id,
        ])->assertRedirect();

        $this->actingAs($sme)->put(route('enterprises.update', $enterprise), [
            'business_name' => 'ورشة النور المحدّثة',
            'location' => 'damascus',
            'sector' => 'صناعة',
            'employee_count' => 3,
            'owner_name' => 'سارة',
            'contact_phone' => '0944000001',
        ])->assertRedirect();

        $this->actingAs($sme)->post(route('enterprises.transactions.store', $enterprise), [
            'type' => 'sale',
            'transaction_date' => '2026-10-05',
            'amount' => '150000',
            'currency' => 'SYP',
            'category' => 'general_sales',
            'payment_status' => 'paid',
            'counterparty' => 'زبون نقدي',
        ])->assertRedirect();

        $transaction = FinancialTransaction::query()->sole();
        $this->assertSame('posted', $transaction->status->value);

        $this->actingAs($sme)->post(route('enterprises.documents.store', $enterprise), [
            'file' => UploadedFile::fake()->create('receipt.pdf', 30, 'application/pdf'),
            'title' => 'إيصال بيع',
            'transaction' => $transaction->public_id,
        ])->assertRedirect();

        $document = Document::query()->sole();
        $this->assertSame($transaction->id, (int) $document->financial_transaction_id);
        $this->actingAs($sme)->get(route('documents.preview', $document))->assertOk();

        $this->publishedRuleSet([
            ['code' => 'records', 'weight' => 1, 'is_mandatory' => false],
        ]);
        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $enterprise))->assertRedirect();
        $assessment = ReadinessAssessment::query()->sole();
        $result = $assessment->results()->firstOrFail();
        $this->actingAs($advisor)->put(route('readiness-assessments.results.update', [$assessment, $result]), [
            'outcome' => 'met',
            'note' => 'السجلات مكتملة',
        ])->assertRedirect();
        $this->actingAs($advisor)->post(route('readiness-assessments.finalize', $assessment))->assertRedirect();
        $this->assertSame('finalized', $assessment->fresh()->status);

        $this->actingAs($sme)->get(route('dashboard.sme'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Sme')
                ->where('enterprise.public_id', $enterprise->public_id)
                ->where('enterprise.business_name', 'ورشة النور المحدّثة')
                ->where('kpis.transaction_count', 1));

        $this->actingAs($sme)->get(route('enterprises.reports.show', $enterprise))->assertOk();
        $this->actingAs($advisor)->get(route('enterprises.show', $enterprise))->assertOk();
        $this->actingAs($admin)->get(route('dashboard.beacon'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('programme.kpis.enterprises_count', 1));
    }

    public function test_cross_tenant_isolation_holds_across_core_resources_in_one_flow(): void
    {
        $enterpriseA = $this->enterprise('أ');
        $enterpriseB = $this->enterprise('ب');
        $smeA = $this->smeOf($enterpriseA);
        $smeB = $this->smeOf($enterpriseB);

        $this->actingAs($smeA)->post(route('enterprises.transactions.store', $enterpriseA), [
            'type' => 'expense',
            'transaction_date' => '2026-10-01',
            'amount' => '10',
            'currency' => 'SYP',
            'category' => 'rent',
            'payment_status' => 'paid',
        ])->assertRedirect();
        $txA = FinancialTransaction::query()->sole();

        $this->actingAs($smeA)->post(route('enterprises.documents.store', $enterpriseA), [
            'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            'title' => 'مستند أ',
        ])->assertRedirect();
        $docA = Document::query()->sole();

        $this->actingAs($smeB)->get(route('enterprises.show', $enterpriseA))->assertForbidden();
        $this->actingAs($smeB)->get(route('transactions.show', $txA))->assertForbidden();
        $this->actingAs($smeB)->get(route('documents.preview', $docA))->assertForbidden();
        $this->actingAs($smeB)->get(route('documents.download', $docA))->assertForbidden();
        $this->actingAs($smeB)->put(route('enterprises.update', $enterpriseA), [
            'business_name' => 'اختراق',
            'location' => 'damascus',
            'contact_phone' => '0900000000',
        ])->assertForbidden();
    }
}
