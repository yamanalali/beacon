<?php

namespace Tests\Feature\Dashboard;

use App\Ai\Models\AiEscalation;
use App\Enums\UserRole;
use App\Models\ComplianceRequirement;
use App\Models\ComplianceRequirementVersion;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\EnterpriseComplianceRecord;
use App\Models\FinancialTransaction;
use App\Models\MfiInstitution;
use App\Models\ReceiptExtraction;
use App\Models\Referral;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardDataTest extends TestCase
{
    use RefreshDatabase;

    private Enterprise $mine;
    private Enterprise $other;
    private User $sme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-16 12:00:00'));
        $this->mine = Enterprise::query()->create(['business_name' => 'منشأتي', 'location' => 'damascus']);
        $this->other = Enterprise::query()->create(['business_name' => 'غيري', 'location' => 'aleppo']);
        $this->sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $this->mine->members()->attach($this->sme->id);
    }

    private function tx(Enterprise $e, string $type, string $amount, string $date, string $currency = 'SYP', string $status = 'posted'): FinancialTransaction
    {
        $t = new FinancialTransaction;
        $t->forceFill([
            'enterprise_id' => $e->id, 'type' => $type, 'transaction_date' => $date, 'amount' => $amount,
            'currency' => $currency, 'category' => 'cat', 'payment_status' => 'paid', 'status' => $status,
        ])->save();

        return $t;
    }

    private function compliance(Enterprise $e, string $due, string $status = 'not_started'): void
    {
        $req = ComplianceRequirement::query()->forceCreate(['public_id' => (string) str()->ulid(), 'code' => 'C'.uniqid(), 'category' => 'tax']);
        $ver = ComplianceRequirementVersion::query()->forceCreate(['compliance_requirement_id' => $req->id, 'version_number' => 1, 'title_ar' => 'تجديد', 'status' => 'published']);
        EnterpriseComplianceRecord::query()->forceCreate([
            'enterprise_id' => $e->id, 'compliance_requirement_id' => $req->id,
            'compliance_requirement_version_id' => $ver->id, 'status' => $status, 'due_date' => $due,
        ]);
    }

    private function extraction(Enterprise $e): void
    {
        $doc = new Document;
        $doc->forceFill(['enterprise_id' => $e->id, 'title' => 'r', 'original_name' => 'r.jpg', 'disk' => 'local', 'path' => 'x', 'mime_type' => 'image/jpeg', 'size' => 1])->save();
        $x = new ReceiptExtraction;
        $x->forceFill(['enterprise_id' => $e->id, 'document_id' => $doc->id, 'status' => 'pending_review', 'extractor' => 'fake'])->save();
    }

    private function referral(Enterprise $e, string $status): void
    {
        $mfi = MfiInstitution::query()->forceCreate(['public_id' => (string) str()->ulid(), 'name_ar' => 'مؤسسة']);
        Referral::query()->forceCreate(['public_id' => (string) str()->ulid(), 'enterprise_id' => $e->id, 'mfi_institution_id' => $mfi->id, 'status' => $status, 'purpose_ar' => 'x', 'disclosed_items' => []]);
    }

    private function seedBoth(): void
    {
        $this->tx($this->mine, 'sale', '1000.50', '2026-10-02');
        $this->tx($this->mine, 'income', '200.00', '2026-10-10');
        $this->tx($this->mine, 'expense', '300.25', '2026-10-05');
        $this->tx($this->mine, 'purchase', '100.00', '2026-09-20');
        $this->tx($this->mine, 'sale', '50.00', '2026-09-01');
        $this->tx($this->mine, 'sale', '999.00', '2026-10-03', 'USD');
        $this->tx($this->mine, 'sale', '777.00', '2026-10-03', 'SYP', 'superseded');
        $this->compliance($this->mine, '2026-10-01');
        $this->compliance($this->mine, '2026-10-20');
        $this->compliance($this->mine, '2026-09-01', 'completed');
        $this->extraction($this->mine);
        AiEscalation::query()->create(['user_id' => $this->sme->id, 'enterprise_id' => $this->mine->id, 'question' => 'q', 'status' => 'open']);

        $this->tx($this->other, 'sale', '50000.00', '2026-10-02');
        $this->tx($this->other, 'expense', '4000.00', '2026-10-02');
        $this->compliance($this->other, '2026-09-01');
        $this->extraction($this->other);
        $this->extraction($this->other);
    }

    public function test_sme_sees_only_own_enterprise_with_correct_sums(): void
    {
        $this->seedBoth();

        $this->actingAs($this->sme)->get('/dashboard/sme')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Dashboard/Sme')
                ->where('enterprise.public_id', $this->mine->public_id)
                ->where('kpis.currency', 'SYP')
                ->where('kpis.current_month.income', '1200.50')
                ->where('kpis.current_month.expense', '300.25')
                ->where('kpis.current_month.net', '900.25')
                ->where('kpis.current_month.count', 3)
                ->where('kpis.previous_month.income', '50.00')
                ->where('kpis.previous_month.expense', '100.00')
                ->where('kpis.previous_month.net', '-50.00')
                ->where('kpis.other_currencies', ['USD'])
                ->where('kpis.transaction_count', 6)
                ->where('kpis.pending_extractions_count', 1)
                ->where('kpis.overdue_compliance_count', 1)
                ->where('kpis.upcoming_compliance_count', 1)
                ->where('kpis.open_escalations_count', 1)
                ->where('kpis.readiness_score', null)
                ->has('recent_transactions', 5)
                ->where('recent_transactions.0.transaction_date', '2026-10-10')
                ->has('recent_transactions.0.url')
                ->has('alerts', 3)
                ->has('recentActivity', 5)
                ->has('upcomingDeadlines', 2));
    }

    public function test_sme_without_enterprise_gets_empty_state(): void
    {
        $lonely = User::factory()->create(['role' => UserRole::SmeUser]);
        $this->actingAs($lonely)->get('/dashboard/sme')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('kpis', null)->where('enterprise', null)
                ->where('recent_transactions', [])->where('alerts', [])->where('readinessStatus', null));
    }

    public function test_sme_with_no_data_gets_zeroes(): void
    {
        $this->actingAs($this->sme)->get('/dashboard/sme')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('kpis.current_month.net', '0.00')
                ->where('kpis.transaction_count', 0)->where('alerts', []));
    }

    public function test_beacon_admin_sees_all_and_advisor_only_assigned(): void
    {
        $this->seedBoth();
        $this->referral($this->mine, 'shared');
        $this->referral($this->other, 'decided');
        $this->referral($this->other, 'decided');

        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);
        $this->actingAs($admin)->get('/dashboard/beacon')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Dashboard/Beacon')
                ->where('indicators', [])
                ->where('programme.scope', 'all')
                ->where('programme.kpis.enterprises_count', 2)
                ->where('programme.kpis.active_enterprises_count', null)
                ->where('programme.kpis.enterprises_by_readiness_band', null)
                ->where('programme.kpis.overdue_compliance_count', 2)
                ->where('programme.kpis.open_escalations_count', 1)
                ->where('programme.kpis.referrals_by_status.1.count', 1)
                ->where('programme.kpis.referrals_by_status.4.count', 2)
                ->has('programme.recent_referrals', 3));

        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $this->other->advisors()->attach($advisor->id);
        $this->actingAs($advisor)->get('/dashboard/beacon')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('programme.scope', 'assigned')
                ->where('programme.kpis.enterprises_count', 1)
                ->where('programme.kpis.overdue_compliance_count', 1)
                ->where('programme.kpis.open_escalations_count', 0)
                ->where('programme.kpis.referrals_by_status.1.count', 0)
                ->where('programme.kpis.referrals_by_status.4.count', 2)
                ->has('programme.recent_referrals', 2)
                ->where('programme.recent_referrals.0.enterprise.public_id', $this->other->public_id));

        $unassigned = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $this->actingAs($unassigned)->get('/dashboard/beacon')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('programme.kpis.enterprises_count', 0)
                ->where('programme.recent_referrals', []));
    }

    public function test_mfi_and_technical_admin_are_forbidden(): void
    {
        foreach ([UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->actingAs($u)->get('/dashboard/sme')->assertForbidden();
            $this->actingAs($u)->get('/dashboard/beacon')->assertForbidden();
        }
    }
}
