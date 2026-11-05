<?php

namespace Tests\Feature\Readiness;

use App\Enums\UserRole;
use App\Models\ReadinessAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadinessFinalizeAuthorizationTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReadiness();
    }

    private function openAssessment(): array
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $this->publishedRuleSet([
            ['code' => 'books', 'weight' => 1, 'is_mandatory' => false],
        ]);
        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $enterprise))->assertRedirect();
        $assessment = ReadinessAssessment::query()->sole();
        $result = $assessment->results()->firstOrFail();
        $this->actingAs($advisor)->put(route('readiness-assessments.results.update', [$assessment, $result]), [
            'outcome' => 'met',
        ])->assertRedirect();

        return [$enterprise, $advisor, $assessment];
    }

    public function test_sme_mfi_unassigned_advisor_and_technical_admin_cannot_finalize(): void
    {
        [$enterprise, , $assessment] = $this->openAssessment();
        $sme = $this->smeOf($enterprise);

        foreach ([
            $sme,
            $this->user(UserRole::MfiUser),
            $this->user(UserRole::BeaconAdvisor),
            $this->user(UserRole::TechnicalAdmin),
        ] as $actor) {
            $this->actingAs($actor)
                ->post(route('readiness-assessments.finalize', $assessment))
                ->assertForbidden();
        }

        $this->assertSame('draft', $assessment->fresh()->status);
    }

    public function test_assigned_advisor_and_admin_can_finalize_and_duplicate_is_rejected(): void
    {
        [$enterprise, $advisor, $assessment] = $this->openAssessment();
        $admin = $this->user(UserRole::BeaconAdmin);

        $this->actingAs($advisor)
            ->post(route('readiness-assessments.finalize', $assessment))
            ->assertRedirect();

        $assessment->refresh();
        $this->assertSame('finalized', $assessment->status);
        $this->assertNotNull($assessment->finalized_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'readiness.assessment_finalized']);

        $this->actingAs($advisor)
            ->post(route('readiness-assessments.finalize', $assessment))
            ->assertStatus(422);

        $this->actingAs($admin)
            ->post(route('readiness-assessments.finalize', $assessment))
            ->assertStatus(422);
    }

    public function test_mandatory_unmet_finalize_sets_flag_false_and_keeps_score_explained(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $this->publishedRuleSet([
            ['code' => 'licence', 'weight' => 3, 'is_mandatory' => true],
            ['code' => 'books', 'weight' => 1, 'is_mandatory' => false],
        ]);
        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $enterprise))->assertRedirect();
        $assessment = ReadinessAssessment::query()->sole();

        $byCode = $assessment->results()->with('criterion')->get()->keyBy(fn ($r) => $r->criterion->code);
        $this->actingAs($advisor)->put(route('readiness-assessments.results.update', [$assessment, $byCode['licence']]), [
            'outcome' => 'not_met',
            'action_needed' => 'إصدار الرخصة',
        ])->assertRedirect();
        $this->actingAs($advisor)->put(route('readiness-assessments.results.update', [$assessment, $byCode['books']]), [
            'outcome' => 'met',
        ])->assertRedirect();

        $this->actingAs($advisor)->post(route('readiness-assessments.finalize', $assessment))->assertRedirect();

        $assessment->refresh();
        $this->assertSame('finalized', $assessment->status);
        $this->assertFalse((bool) $assessment->mandatory_conditions_met);
        $this->assertNotNull($assessment->score);
        $this->assertIsArray($assessment->summary);
    }
}
