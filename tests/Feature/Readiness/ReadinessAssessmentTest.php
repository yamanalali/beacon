<?php

namespace Tests\Feature\Readiness;

use App\Enums\UserRole;
use App\Models\EnterpriseComplianceRecord;
use App\Models\ReadinessAssessment;
use App\Models\ReadinessRuleSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class ReadinessAssessmentTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReadiness();
    }

    public function test_only_admin_manages_rule_sets_and_published_sets_are_frozen(): void
    {
        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $this->actingAs($this->user($role))->post(route('admin.readiness.store'), ['name_ar' => 'س'])->assertForbidden();
        }

        $admin = $this->user(UserRole::BeaconAdmin);
        $this->actingAs($admin)->post(route('admin.readiness.store'), ['name_ar' => 'قواعد تجريبية'])->assertRedirect();
        $set = ReadinessRuleSet::query()->firstOrFail();

        $this->actingAs($admin)->post(route('admin.readiness.publish', $set))->assertStatus(422); // no criteria

        $this->actingAs($admin)->post(route('admin.readiness.criteria.store', $set), [
            'code' => 'c1', 'label_ar' => 'معيار', 'weight' => 2, 'is_mandatory' => true, 'missing_data_treatment' => 'needs_review',
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.readiness.publish', $set))->assertSessionHasErrors('bands'); // bands required to publish
        $set->bands()->create(['label_ar' => 'band', 'min' => 0, 'max' => 100, 'color_token' => 'success', 'sort_order' => 1]);
        $this->actingAs($admin)->post(route('admin.readiness.publish', $set))->assertRedirect();
        $this->assertSame('published', $set->fresh()->status);

        $this->actingAs($admin)->post(route('admin.readiness.criteria.store', $set), [
            'code' => 'c2', 'label_ar' => 'معيار', 'weight' => 1, 'is_mandatory' => false, 'missing_data_treatment' => 'excluded',
        ])->assertStatus(422);
        $this->assertDatabaseCount('readiness_criteria', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'readiness.rule_set_published']);
    }

    public function test_assessment_requires_published_rules(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);

        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $enterprise))->assertSessionHasErrors('rule_set');
        $this->assertDatabaseCount('readiness_assessments', 0);
    }

    public function test_advisor_assesses_assigns_actions_and_finalizes_with_explained_score(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $sme = $this->smeOf($enterprise);
        $requirement = $this->publishedRequirement();
        EnterpriseComplianceRecord::query()->create([
            'enterprise_id' => $enterprise->id, 'compliance_requirement_id' => $requirement->id,
            'compliance_requirement_version_id' => $requirement->currentVersion()->id, 'status' => 'completed',
        ]);
        $this->publishedRuleSet([
            ['code' => 'licence', 'weight' => 3, 'compliance_requirement_id' => $requirement->id, 'is_mandatory' => true],
            ['code' => 'books', 'weight' => 1],
            ['code' => 'other', 'weight' => 5, 'missing_data_treatment' => 'excluded'],
        ]);

        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $enterprise))->assertRedirect();
        $assessment = ReadinessAssessment::query()->firstOrFail();
        $licence = $assessment->results()->whereHas('criterion', fn ($q) => $q->where('code', 'licence'))->firstOrFail();
        $books = $assessment->results()->whereHas('criterion', fn ($q) => $q->where('code', 'books'))->firstOrFail();
        $this->assertSame('met', $licence->outcome);
        $this->assertSame($requirement->currentVersion()->id, $licence->compliance_requirement_version_id);

        $this->actingAs($advisor)->put(route('readiness-assessments.results.update', [$assessment, $books]), [
            'outcome' => 'not_met', 'action_needed' => 'تنظيم الدفاتر', 'responsible_user_id' => $sme->id, 'target_date' => '2026-11-30',
        ])->assertRedirect();
        // responsible person must belong to the enterprise
        $outsider = $this->user(UserRole::SmeUser);
        $this->actingAs($advisor)->put(route('readiness-assessments.results.update', [$assessment, $books]), [
            'outcome' => 'not_met', 'responsible_user_id' => $outsider->id,
        ])->assertSessionHasErrors('responsible_user_id');

        $this->actingAs($sme)->put(route('readiness-assessments.results.update', [$assessment, $books]), ['outcome' => 'met'])->assertForbidden();

        $this->actingAs($advisor)->post(route('readiness-assessments.finalize', $assessment))->assertRedirect();
        $assessment->refresh();
        $this->assertSame('finalized', $assessment->status);
        $this->assertEquals(75.0, (float) $assessment->score); // 3 of 4 counted weight; "other" excluded
        $this->assertTrue($assessment->mandatory_conditions_met);

        $this->actingAs($sme)->get(route('readiness-assessments.show', $assessment))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Readiness/Assessment')->where('canEdit', false)->has('disclaimer'));

        $this->actingAs($advisor)->put(route('readiness-assessments.results.update', [$assessment, $books]), ['outcome' => 'met'])->assertStatus(422);
        $this->expectException(LogicException::class);
        $assessment->update(['score' => 100]);
    }

    public function test_old_assessment_keeps_its_rule_set_after_new_rules_are_published(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $v1 = $this->publishedRuleSet([['code' => 'a', 'weight' => 1]], 1);
        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $enterprise));
        $old = ReadinessAssessment::query()->firstOrFail();
        $this->actingAs($advisor)->post(route('readiness-assessments.finalize', $old));

        $v1->update(['status' => ReadinessRuleSet::STATUS_RETIRED]);
        $v2 = $this->publishedRuleSet([['code' => 'b', 'weight' => 1], ['code' => 'c', 'weight' => 1]], 2);
        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $enterprise));

        $old->refresh();
        $this->assertSame($v1->id, $old->readiness_rule_set_id);
        $this->assertSame(1, $old->results()->count());
        $this->assertSame($v2->id, ReadinessAssessment::query()->latest('id')->first()->readiness_rule_set_id);
    }

    public function test_readiness_access_isolation(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $this->publishedRuleSet([['code' => 'a', 'weight' => 1]]);
        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $enterprise));
        $assessment = ReadinessAssessment::query()->firstOrFail();

        $otherSme = $this->smeOf($this->enterprise('ب'));
        $this->actingAs($otherSme)->get(route('readiness-assessments.show', $assessment))->assertForbidden();
        $this->actingAs($otherSme)->get(route('enterprises.readiness.index', $enterprise))->assertForbidden();
        $this->actingAs($this->user(UserRole::BeaconAdvisor))->post(route('enterprises.readiness.store', $enterprise))->assertForbidden();
        $this->actingAs($this->user(UserRole::MfiUser))->get(route('readiness-assessments.show', $assessment))->assertForbidden();
        $this->actingAs($this->user(UserRole::TechnicalAdmin))->get(route('readiness-assessments.show', $assessment))->assertForbidden();
        $this->actingAs($this->smeOf($enterprise))->post(route('enterprises.readiness.store', $enterprise))->assertForbidden();
    }
}
