<?php

namespace Tests\Feature\Readiness;

use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\EnterpriseComplianceRecord;
use App\Models\MfiInstitution;
use App\Models\ReadinessAssessment;
use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MfiAndReferralTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReadiness();
    }

    public function test_only_admin_manages_mfi_directory(): void
    {
        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $this->actingAs($this->user($role))->get(route('admin.mfis.index'))->assertForbidden();
            $this->actingAs($this->user($role))->post(route('admin.mfis.store'), ['name_ar' => 'س'])->assertForbidden();
        }
        $admin = $this->user(UserRole::BeaconAdmin);
        $this->actingAs($admin)->post(route('admin.mfis.store'), ['name_ar' => 'مؤسسة'])->assertRedirect();
        $institution = MfiInstitution::query()->firstOrFail();
        $this->actingAs($admin)->post(route('admin.mfis.criteria.store', $institution), ['label_ar' => 'حد أدنى', 'type' => 'min_readiness_score', 'min_score' => 60])->assertRedirect();
        $this->assertEquals(['min_score' => 60], $institution->eligibilityCriteria()->first()->parameters);

        $mfiUser = $this->user(UserRole::MfiUser);
        $this->actingAs($admin)->post(route('admin.mfis.users.attach', $institution), ['user_id' => $mfiUser->id])->assertRedirect();
        $this->assertSame($institution->id, (int) $mfiUser->fresh()->mfi_institution_id);
        $sme = $this->user(UserRole::SmeUser);
        $this->actingAs($admin)->post(route('admin.mfis.users.attach', $institution), ['user_id' => $sme->id])->assertSessionHasErrors('user_id');
        $this->assertDatabaseHas('audit_logs', ['action' => 'mfi.criterion_added']);
    }

    public function test_matching_distinguishes_met_not_met_and_needs_review(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $requirement = $this->publishedRequirement();
        EnterpriseComplianceRecord::query()->create([
            'enterprise_id' => $enterprise->id, 'compliance_requirement_id' => $requirement->id,
            'compliance_requirement_version_id' => $requirement->currentVersion()->id, 'status' => 'in_progress',
        ]);
        $this->publishedRuleSet([['code' => 'a', 'weight' => 1]]);
        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $enterprise));
        $assessment = ReadinessAssessment::query()->firstOrFail();
        $assessment->results()->first()->update(['outcome' => 'met']);
        $this->actingAs($advisor)->post(route('readiness-assessments.finalize', $assessment));

        $institution = $this->institution();
        $institution->eligibilityCriteria()->create(['label_ar' => 'جاهزية', 'type' => 'min_readiness_score', 'parameters' => ['min_score' => 50]]);
        $institution->eligibilityCriteria()->create(['label_ar' => 'رخصة', 'type' => 'compliance_requirement_completed', 'parameters' => ['compliance_requirement_id' => $requirement->id]]);
        $institution->eligibilityCriteria()->create(['label_ar' => 'زيارة', 'type' => 'manual_review']);

        $this->actingAs($advisor)->get(route('enterprises.mfi-matching', $enterprise))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Mfi/Matching')
                ->where('matches.0.results.0.result', 'met')
                ->where('matches.0.results.1.result', 'not_met')
                ->where('matches.0.results.2.result', 'needs_review')
                ->has('disclaimer'));

        $this->actingAs($this->smeOf($enterprise))->get(route('enterprises.mfi-matching', $enterprise))->assertForbidden();
        $this->actingAs($this->user(UserRole::MfiUser))->get(route('enterprises.mfi-matching', $enterprise))->assertForbidden();
        $this->actingAs($this->user(UserRole::BeaconAdvisor))->get(route('enterprises.mfi-matching', $enterprise))->assertForbidden();
    }

    /** @return array{Enterprise, User, User, MfiInstitution, User, Referral} */
    private function draftReferral(): array
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $sme = $this->smeOf($enterprise);
        $institution = $this->institution();
        $mfi = $this->mfiUserOf($institution);

        $this->actingAs($advisor)->post(route('enterprises.referrals.store', $enterprise), [
            'mfi_institution_id' => $institution->id,
            'purpose_ar' => 'طلب تمويل رأس مال عامل',
            'disclosed_items' => ['enterprise_profile', 'compliance_status'],
        ])->assertRedirect();

        return [$enterprise, $advisor, $sme, $institution, $mfi, Referral::query()->latest('id')->firstOrFail()];
    }

    public function test_full_referral_workflow_with_consent(): void
    {
        [$enterprise, $advisor, $sme, $institution, $mfi, $referral] = $this->draftReferral();

        // MFI cannot see draft
        $this->actingAs($mfi)->get(route('referrals.show', $referral))->assertForbidden();

        // cannot share without consent
        $this->actingAs($advisor)->post(route('referrals.share', $referral))->assertSessionHasErrors('consent');
        $this->assertSame('draft', $referral->fresh()->status);

        // SME sees institution and disclosed items before consenting
        $this->actingAs($sme)->get(route('referrals.show', $referral))->assertOk()
            ->assertInertia(fn ($page) => $page->where('referral.institution', $institution->name_ar)
                ->has('referral.disclosed_items', 2)->where('can.consent', true));

        // consent must be explicit
        $this->actingAs($sme)->post(route('referrals.consent', $referral), [])->assertSessionHasErrors('confirm');
        // advisor cannot consent on SME's behalf
        $this->actingAs($advisor)->post(route('referrals.consent', $referral), ['confirm' => true])->assertForbidden();

        $this->actingAs($sme)->post(route('referrals.consent', $referral), ['confirm' => true])->assertRedirect();
        $this->assertDatabaseHas('referral_consents', [
            'referral_id' => $referral->id, 'mfi_institution_id' => $institution->id, 'status' => 'granted', 'granted_by' => $sme->id,
        ]);

        $this->actingAs($advisor)->post(route('referrals.share', $referral))->assertRedirect();
        $this->assertSame('shared', $referral->fresh()->status);

        $this->actingAs($mfi)->get(route('mfi.referrals.index'))->assertOk()->assertInertia(fn ($page) => $page->has('referrals', 1));
        $this->actingAs($mfi)->get(route('referrals.show', $referral))->assertOk()
            ->assertInertia(fn ($page) => $page->has('package.enterprise_profile')->missing('package.readiness_assessment'));

        $this->actingAs($mfi)->post(route('referrals.status', $referral), ['status' => 'info_requested', 'note' => 'كشف حساب'])->assertRedirect();
        $this->actingAs($mfi)->post(route('referrals.status', $referral), ['status' => 'under_review'])->assertRedirect();
        $this->actingAs($mfi)->post(route('referrals.status', $referral), ['status' => 'outcome_recorded'])->assertSessionHasErrors('status');
        $this->actingAs($mfi)->post(route('referrals.status', $referral), ['status' => 'decided', 'note' => 'موافقة مبدئية'])->assertRedirect();
        $this->actingAs($sme)->post(route('referrals.outcome', $referral), ['outcome_note' => 'x'])->assertForbidden();
        $this->actingAs($mfi)->post(route('referrals.outcome', $referral), ['outcome_note' => 'تم صرف التمويل'])->assertRedirect();

        $referral->refresh();
        $this->assertSame('outcome_recorded', $referral->status);
        $this->assertSame(
            ['draft', 'shared', 'info_requested', 'under_review', 'decided', 'outcome_recorded'],
            $referral->statusChanges()->pluck('to_status')->all()
        );
        foreach (['referral.created', 'consent.granted', 'referral.shared', 'referral.status_changed', 'referral.outcome_recorded'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action]);
        }
    }

    public function test_mfi_a_cannot_access_mfi_b_referral(): void
    {
        [, $advisor, $sme, , , $referral] = $this->draftReferral();
        $this->actingAs($sme)->post(route('referrals.consent', $referral), ['confirm' => true]);
        $this->actingAs($advisor)->post(route('referrals.share', $referral));

        $otherMfi = $this->mfiUserOf($this->institution('مؤسسة ب'));
        $this->actingAs($otherMfi)->get(route('referrals.show', $referral))->assertForbidden();
        $this->actingAs($otherMfi)->post(route('referrals.status', $referral), ['status' => 'under_review'])->assertForbidden();
        $this->actingAs($otherMfi)->get(route('mfi.referrals.index'))->assertInertia(fn ($page) => $page->has('referrals', 0));

        $unlinkedMfi = $this->user(UserRole::MfiUser);
        $this->actingAs($unlinkedMfi)->get(route('referrals.show', $referral))->assertForbidden();
        $this->actingAs($unlinkedMfi)->get(route('mfi.referrals.index'))->assertForbidden();
    }

    public function test_withdrawn_consent_blocks_further_mfi_access(): void
    {
        [, $advisor, $sme, , $mfi, $referral] = $this->draftReferral();
        $this->actingAs($sme)->post(route('referrals.consent', $referral), ['confirm' => true]);
        $this->actingAs($advisor)->post(route('referrals.share', $referral));
        $this->actingAs($mfi)->get(route('referrals.show', $referral))->assertOk();

        $this->actingAs($sme)->post(route('referrals.consent.withdraw', $referral))->assertRedirect();
        $this->assertDatabaseHas('referral_consents', ['referral_id' => $referral->id, 'status' => 'withdrawn']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'consent.withdrawn']);

        $this->actingAs($mfi)->get(route('referrals.show', $referral))->assertForbidden();
        $this->actingAs($mfi)->get(route('mfi.referrals.index'))->assertInertia(fn ($page) => $page->has('referrals', 0));
    }

    public function test_referral_access_per_role(): void
    {
        [$enterprise, , , $institution, , $referral] = $this->draftReferral();
        $otherSme = $this->smeOf($this->enterprise('ب'));

        $this->actingAs($otherSme)->get(route('referrals.show', $referral))->assertForbidden();
        $this->actingAs($otherSme)->post(route('referrals.consent', $referral), ['confirm' => true])->assertForbidden();
        $this->actingAs($this->user(UserRole::BeaconAdvisor))->get(route('referrals.show', $referral))->assertForbidden();
        $this->actingAs($this->user(UserRole::TechnicalAdmin))->get(route('referrals.show', $referral))->assertForbidden();
        $this->actingAs($this->user(UserRole::BeaconAdmin))->get(route('referrals.show', $referral))->assertOk();
        $this->actingAs($this->smeOf($enterprise))->post(route('enterprises.referrals.store', $enterprise), [
            'mfi_institution_id' => $institution->id, 'purpose_ar' => 'x', 'disclosed_items' => ['enterprise_profile'],
        ])->assertForbidden();
        $this->actingAs($this->user(UserRole::MfiUser, ['mfi_institution_id' => $institution->id]))
            ->get(route('enterprises.referrals.index', $enterprise))->assertForbidden();
    }

    public function test_disclosed_items_must_be_from_approved_list(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $institution = $this->institution();

        $this->actingAs($advisor)->post(route('enterprises.referrals.store', $enterprise), [
            'mfi_institution_id' => $institution->id, 'purpose_ar' => 'x', 'disclosed_items' => ['bank_statements'],
        ])->assertSessionHasErrors('disclosed_items.0');
        $this->actingAs($advisor)->post(route('enterprises.referrals.store', $enterprise), [
            'mfi_institution_id' => $institution->id, 'purpose_ar' => 'x', 'disclosed_items' => ['readiness_assessment'],
        ])->assertSessionHasErrors('readiness_assessment_id');
        $this->assertDatabaseCount('referrals', 0);
    }
}
