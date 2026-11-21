<?php

namespace Tests\Feature\Readiness;

use App\Enums\UserRole;
use App\Models\ComplianceRequirement;
use App\Models\ComplianceRequirementVersion;
use App\Models\MfiEligibilityCriterion;
use App\Models\ReadinessAssessment;
use App\Models\ReadinessRuleSet;
use App\Models\Referral;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Batch C: edit/delete gaps for compliance, readiness, MFI configuration and referrals. */
class EditDeleteBatchCTest extends TestCase
{
    use ReadinessTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReadiness();
    }

    // ---- C6 compliance reactivate ----

    public function test_admin_reactivates_inactive_requirement_and_others_cannot(): void
    {
        $admin = $this->user(UserRole::BeaconAdmin);
        $new = $this->publishedRequirement('NEW');
        $requirement = $this->publishedRequirement('OLD');
        $this->actingAs($admin)->post(route('admin.compliance.replace', $requirement), ['replacement_public_id' => $new->public_id])->assertRedirect();
        $this->assertFalse($requirement->fresh()->is_active);

        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $this->actingAs($this->user($role))->post(route('admin.compliance.reactivate', $requirement))->assertForbidden();
        }
        $this->assertFalse($requirement->fresh()->is_active);

        $this->actingAs($admin)->post(route('admin.compliance.reactivate', $requirement))->assertRedirect(route('admin.compliance.index'));
        $fresh = $requirement->fresh();
        $this->assertTrue($fresh->is_active);
        $this->assertNull($fresh->replaced_by_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'compliance.requirement_reactivated', 'actor_id' => $admin->id]);

        // already active -> rejected
        $this->actingAs($admin)->post(route('admin.compliance.reactivate', $requirement))->assertStatus(422);
    }

    // ---- C7-8 version routes used by the admin UI ----

    public function test_new_version_edit_draft_and_single_open_draft_rule(): void
    {
        $admin = $this->user(UserRole::BeaconAdmin);
        $requirement = $this->publishedRequirement('VER');

        $this->actingAs($admin)->post(route('admin.compliance.versions.store', $requirement), ['title_ar' => 'نسخة ٢'])->assertSessionHasErrors('change_summary');
        $this->actingAs($admin)->post(route('admin.compliance.versions.store', $requirement), ['title_ar' => 'نسخة ٢', 'change_summary' => 'تغيير'])->assertRedirect();
        $draft = $requirement->versions()->where('version_number', 2)->firstOrFail();
        $this->assertSame(ComplianceRequirementVersion::STATUS_DRAFT, $draft->status);

        $this->actingAs($admin)->put(route('admin.compliance.versions.update', $draft), ['title_ar' => 'نسخة ٢ معدلة', 'guidance_ar' => 'إرشاد'])->assertRedirect();
        $this->assertSame('نسخة ٢ معدلة', $draft->fresh()->title_ar);

        // only one open draft at a time
        $this->actingAs($admin)->post(route('admin.compliance.versions.store', $requirement), ['title_ar' => 'ثالثة', 'change_summary' => 'x'])->assertStatus(422);
    }

    // ---- C9 readiness draft destroy / retire ----

    public function test_admin_deletes_draft_rule_set_with_children_but_not_published(): void
    {
        $admin = $this->user(UserRole::BeaconAdmin);
        $draft = ReadinessRuleSet::query()->create(['version_number' => 1, 'name_ar' => 'مسودة', 'status' => ReadinessRuleSet::STATUS_DRAFT]);
        $draft->criteria()->create(['code' => 'a', 'label_ar' => 'أ', 'weight' => 1, 'missing_data_treatment' => 'counts_as_unmet', 'is_mandatory' => false]);
        $draft->pillars()->create(['code' => 'p', 'label_ar' => 'محور', 'weight' => 1]);
        $draft->bands()->create(['label_ar' => 'شريحة', 'min' => 0, 'max' => 100, 'color_token' => 'success']);

        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $this->actingAs($this->user($role))->delete(route('admin.readiness.destroy', $draft))->assertForbidden();
        }
        $this->assertDatabaseHas('readiness_rule_sets', ['id' => $draft->id]);

        $this->actingAs($admin)->delete(route('admin.readiness.destroy', $draft))->assertRedirect(route('admin.readiness.index'));
        $this->assertDatabaseMissing('readiness_rule_sets', ['id' => $draft->id]);
        $this->assertDatabaseCount('readiness_criteria', 0);
        $this->assertDatabaseCount('readiness_pillars', 0);
        $this->assertDatabaseCount('readiness_score_bands', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'readiness.rule_set_deleted']);
    }

    public function test_published_rule_set_cannot_be_deleted_but_can_be_retired(): void
    {
        $admin = $this->user(UserRole::BeaconAdmin);
        $published = $this->publishedRuleSet([['code' => 'a', 'weight' => 1]]);

        $this->actingAs($admin)->delete(route('admin.readiness.destroy', $published))->assertStatus(422);
        $this->assertDatabaseHas('readiness_rule_sets', ['id' => $published->id, 'status' => 'published']);
        $this->assertDatabaseCount('readiness_criteria', 1);

        $this->actingAs($this->user(UserRole::BeaconAdvisor))->post(route('admin.readiness.retire', $published))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.readiness.retire', $published))->assertRedirect();
        $this->assertSame(ReadinessRuleSet::STATUS_RETIRED, $published->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'readiness.rule_set_retired']);

        // retired sets can be neither deleted nor retired again
        $this->actingAs($admin)->delete(route('admin.readiness.destroy', $published))->assertStatus(422);
        $this->actingAs($admin)->post(route('admin.readiness.retire', $published))->assertStatus(422);
    }

    // ---- C10 void readiness assessment ----

    public function test_only_draft_assessments_can_be_voided_by_staff_in_scope(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $sme = $this->smeOf($enterprise);
        $this->publishedRuleSet([['code' => 'a', 'weight' => 1]]);

        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $enterprise))->assertRedirect();
        $assessment = ReadinessAssessment::query()->firstOrFail();

        $this->actingAs($sme)->post(route('readiness-assessments.void', $assessment))->assertForbidden();
        $this->actingAs($this->advisorOf($this->enterprise('أخرى')))->post(route('readiness-assessments.void', $assessment))->assertForbidden();
        $this->assertSame(ReadinessAssessment::STATUS_DRAFT, $assessment->fresh()->status);

        $this->actingAs($advisor)->get(route('readiness-assessments.show', $assessment))->assertInertia(fn ($page) => $page->where('canVoid', true));
        $this->actingAs($advisor)->post(route('readiness-assessments.void', $assessment))->assertRedirect(route('enterprises.readiness.index', $enterprise));
        $this->assertSame(ReadinessAssessment::STATUS_VOIDED, $assessment->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'readiness.assessment_voided', 'actor_id' => $advisor->id]);

        // voided is frozen: no editing, finalizing or voiding again
        $result = $assessment->results()->firstOrFail();
        $this->actingAs($advisor)->put(route('readiness-assessments.results.update', [$assessment, $result]), ['outcome' => 'met'])->assertStatus(422);
        $this->actingAs($advisor)->post(route('readiness-assessments.finalize', $assessment))->assertStatus(422);
        $this->actingAs($advisor)->post(route('readiness-assessments.void', $assessment))->assertStatus(422);
        $this->actingAs($advisor)->get(route('readiness-assessments.show', $assessment))->assertInertia(fn ($page) => $page->where('canEdit', false)->where('canVoid', false));
    }

    public function test_finalized_assessment_cannot_be_voided(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $this->publishedRuleSet([['code' => 'a', 'weight' => 1]]);
        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $enterprise));
        $assessment = ReadinessAssessment::query()->firstOrFail();
        $this->actingAs($advisor)->post(route('readiness-assessments.finalize', $assessment))->assertRedirect();

        $this->actingAs($advisor)->post(route('readiness-assessments.void', $assessment))->assertStatus(422);
        $this->assertSame(ReadinessAssessment::STATUS_FINALIZED, $assessment->fresh()->status);
    }

    // ---- C11 MFI criterion update ----

    public function test_admin_updates_mfi_criterion_and_parameters_follow_type(): void
    {
        $admin = $this->user(UserRole::BeaconAdmin);
        $institution = $this->institution();
        $criterion = $institution->eligibilityCriteria()->create(['label_ar' => 'قديم', 'type' => 'min_readiness_score', 'parameters' => ['min_score' => 50]]);

        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $this->actingAs($this->user($role))->put(route('admin.mfis.criteria.update', $criterion), ['label_ar' => 'x', 'type' => 'manual_review'])->assertForbidden();
        }

        $this->actingAs($admin)->put(route('admin.mfis.criteria.update', $criterion), ['label_ar' => 'جديد', 'type' => 'min_readiness_score'])->assertSessionHasErrors('min_score');
        $this->actingAs($admin)->put(route('admin.mfis.criteria.update', $criterion), ['label_ar' => 'جديد', 'type' => 'min_readiness_score', 'min_score' => 70])->assertRedirect(route('admin.mfis.index'));
        $criterion->refresh();
        $this->assertSame('جديد', $criterion->label_ar);
        $this->assertEquals(['min_score' => 70.0], $criterion->parameters);
        $this->assertSame($institution->id, (int) $criterion->mfi_institution_id);

        $this->actingAs($admin)->put(route('admin.mfis.criteria.update', $criterion), ['label_ar' => 'يدوي', 'type' => 'manual_review'])->assertRedirect();
        $this->assertNull($criterion->fresh()->parameters);
        $this->assertSame('manual_review', $criterion->fresh()->type);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mfi.criterion_updated', 'actor_id' => $admin->id]);

        $this->assertInstanceOf(MfiEligibilityCriterion::class, $criterion);
    }

    // ---- C12 detach MFI user ----

    public function test_admin_detaches_mfi_user_and_user_loses_referral_access(): void
    {
        $admin = $this->user(UserRole::BeaconAdmin);
        $institution = $this->institution();
        $mfiUser = $this->mfiUserOf($institution);

        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $this->actingAs($this->user($role))->delete(route('admin.mfis.users.detach', $mfiUser))->assertForbidden();
        }
        $this->assertSame($institution->id, (int) $mfiUser->fresh()->mfi_institution_id);

        $this->actingAs($admin)->delete(route('admin.mfis.users.detach', $mfiUser))->assertRedirect(route('admin.mfis.index'));
        $this->assertNull($mfiUser->fresh()->mfi_institution_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mfi.user_detached', 'actor_id' => $admin->id]);

        // not attached any more, and non-MFI users cannot be "detached"
        $this->actingAs($admin)->delete(route('admin.mfis.users.detach', $mfiUser))->assertStatus(422);
        $this->actingAs($admin)->delete(route('admin.mfis.users.detach', $this->user(UserRole::SmeUser)))->assertStatus(422);
    }

    // ---- C13 referral cancel + draft update ----

    /** @return array{0: \App\Models\Enterprise, 1: \App\Models\User, 2: \App\Models\User, 3: Referral} */
    private function draftReferral(): array
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $sme = $this->smeOf($enterprise);
        $institution = $this->institution();
        $this->actingAs($advisor)->post(route('enterprises.referrals.store', $enterprise), [
            'mfi_institution_id' => $institution->id,
            'purpose_ar' => 'رأس مال عامل',
            'disclosed_items' => ['enterprise_profile'],
        ])->assertRedirect();

        return [$enterprise, $advisor, $sme, Referral::query()->firstOrFail()];
    }

    public function test_staff_updates_draft_referral_fields_only(): void
    {
        [$enterprise, $advisor, $sme, $referral] = $this->draftReferral();
        $otherInstitution = $this->institution('مؤسسة ب');

        $payload = ['purpose_ar' => 'غرض معدل', 'disclosed_items' => ['enterprise_profile', 'compliance_status']];
        $this->actingAs($sme)->put(route('referrals.update', $referral), $payload)->assertForbidden();
        $this->actingAs($this->advisorOf($this->enterprise('أخرى')))->put(route('referrals.update', $referral), $payload)->assertForbidden();

        $this->actingAs($advisor)->put(route('referrals.update', $referral), $payload + [
            'mfi_institution_id' => $otherInstitution->id, 'status' => 'shared', 'enterprise_id' => 999,
        ])->assertRedirect(route('referrals.show', $referral));
        $fresh = $referral->fresh();
        $this->assertSame('غرض معدل', $fresh->purpose_ar);
        $this->assertSame(['enterprise_profile', 'compliance_status'], $fresh->disclosed_items);
        $this->assertSame(Referral::STATUS_DRAFT, $fresh->status);
        $this->assertSame($referral->mfi_institution_id, $fresh->mfi_institution_id);
        $this->assertSame($enterprise->id, (int) $fresh->enterprise_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'referral.updated', 'actor_id' => $advisor->id]);

        $this->actingAs($advisor)->put(route('referrals.update', $referral), ['purpose_ar' => '', 'disclosed_items' => []])->assertSessionHasErrors(['purpose_ar', 'disclosed_items']);
        $this->actingAs($advisor)->put(route('referrals.update', $referral), ['purpose_ar' => 'س', 'disclosed_items' => ['readiness_assessment']])->assertSessionHasErrors('readiness_assessment_id');
    }

    public function test_referral_update_blocked_while_consent_active(): void
    {
        [, $advisor, $sme, $referral] = $this->draftReferral();
        $this->actingAs($sme)->post(route('referrals.consent', $referral), ['confirm' => true])->assertRedirect();

        $this->actingAs($advisor)->put(route('referrals.update', $referral), ['purpose_ar' => 'جديد', 'disclosed_items' => ['documents']])->assertStatus(422);
        $this->assertSame('رأس مال عامل', $referral->fresh()->purpose_ar);
    }

    public function test_staff_cancels_draft_referral_and_consent_is_withdrawn(): void
    {
        [$enterprise, $advisor, $sme, $referral] = $this->draftReferral();
        $this->actingAs($sme)->post(route('referrals.consent', $referral), ['confirm' => true]);
        $this->assertNotNull($referral->activeConsent());

        $this->actingAs($sme)->post(route('referrals.cancel', $referral))->assertForbidden();
        $this->actingAs($this->advisorOf($this->enterprise('أخرى')))->post(route('referrals.cancel', $referral))->assertForbidden();
        $this->assertSame(Referral::STATUS_DRAFT, $referral->fresh()->status);

        $this->actingAs($advisor)->post(route('referrals.cancel', $referral))->assertRedirect(route('enterprises.referrals.index', $enterprise));
        $fresh = $referral->fresh();
        $this->assertSame(Referral::STATUS_CANCELLED, $fresh->status);
        $this->assertNull($fresh->activeConsent());
        $this->assertDatabaseHas('referral_status_changes', ['referral_id' => $referral->id, 'from_status' => 'draft', 'to_status' => 'cancelled']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'referral.cancelled', 'actor_id' => $advisor->id]);

        // cancelled referrals cannot be edited, shared, cancelled again or consented to
        $this->actingAs($advisor)->put(route('referrals.update', $referral), ['purpose_ar' => 'x', 'disclosed_items' => ['documents']])->assertStatus(422);
        $this->actingAs($advisor)->post(route('referrals.share', $referral))->assertStatus(422);
        $this->actingAs($advisor)->post(route('referrals.cancel', $referral))->assertStatus(422);
        $this->actingAs($sme)->post(route('referrals.consent', $referral), ['confirm' => true])->assertStatus(422);
        $this->assertSame(Referral::STATUS_CANCELLED, $referral->fresh()->status);
    }

    public function test_shared_referral_cannot_be_cancelled_or_updated(): void
    {
        [, $advisor, $sme, $referral] = $this->draftReferral();
        $this->actingAs($sme)->post(route('referrals.consent', $referral), ['confirm' => true]);
        $this->actingAs($advisor)->post(route('referrals.share', $referral))->assertRedirect();
        $this->assertSame(Referral::STATUS_SHARED, $referral->fresh()->status);

        $this->actingAs($advisor)->post(route('referrals.cancel', $referral))->assertStatus(422);
        $this->actingAs($advisor)->put(route('referrals.update', $referral), ['purpose_ar' => 'جديد', 'disclosed_items' => ['documents']])->assertStatus(422);
        $this->actingAs($this->user(UserRole::BeaconAdmin))->post(route('referrals.cancel', $referral))->assertStatus(422);

        $fresh = $referral->fresh();
        $this->assertSame(Referral::STATUS_SHARED, $fresh->status);
        $this->assertSame('رأس مال عامل', $fresh->purpose_ar);
        $this->assertNotNull($fresh->activeConsent());
    }

    public function test_cancelled_referral_is_hidden_from_mfi_user(): void
    {
        [, $advisor, $sme, $referral] = $this->draftReferral();
        $mfiUser = $this->mfiUserOf($referral->institution);
        $this->actingAs($advisor)->post(route('referrals.cancel', $referral))->assertRedirect();

        $this->actingAs($mfiUser)->get(route('referrals.show', $referral))->assertForbidden();
        $this->actingAs($mfiUser)->get(route('mfi.referrals.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->has('referrals', 0));
    }

    public function test_referral_show_exposes_draft_actions_to_staff_only(): void
    {
        [, $advisor, $sme, $referral] = $this->draftReferral();

        $this->actingAs($advisor)->get(route('referrals.show', $referral))->assertInertia(fn ($page) => $page
            ->where('can.update', true)->where('can.cancel', true)->has('edit.disclosableItems'));
        $this->actingAs($sme)->get(route('referrals.show', $referral))->assertInertia(fn ($page) => $page
            ->where('can.update', false)->where('can.cancel', false)->where('edit', null));
    }
}
