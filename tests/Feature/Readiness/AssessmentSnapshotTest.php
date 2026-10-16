<?php

namespace Tests\Feature\Readiness;

use App\Enums\UserRole;
use App\Models\ReadinessAssessment;
use App\Models\ReadinessAssessmentSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssessmentSnapshotTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReadiness();
    }

    /** @return array{0:\App\Models\Enterprise,1:\App\Models\User,2:ReadinessAssessment} */
    private function finalizedAssessment(): array
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $this->publishedRuleSet([['code' => 'a', 'weight' => 1], ['code' => 'b', 'weight' => 1]]);
        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $enterprise))->assertRedirect();
        $assessment = ReadinessAssessment::query()->firstOrFail();
        $a = $assessment->results()->whereHas('criterion', fn ($q) => $q->where('code', 'a'))->firstOrFail();
        $this->actingAs($advisor)->put(route('readiness-assessments.results.update', [$assessment, $a]), ['outcome' => 'met']);
        $this->actingAs($advisor)->post(route('readiness-assessments.finalize', $assessment))->assertRedirect();

        return [$enterprise, $advisor, $assessment->fresh()];
    }

    private function bumpWeight(string $code, int $weight): void
    {
        // Simulates rule edits (e.g. scoring-matrix v2) bypassing the model guard.
        DB::table('readiness_criteria')->where('code', $code)->update(['weight' => $weight]);
    }

    public function test_finalize_creates_initial_snapshot_with_full_evaluation_and_config(): void
    {
        [, $advisor, $assessment] = $this->finalizedAssessment();

        $snap = ReadinessAssessmentSnapshot::query()->sole();
        $this->assertSame('initial', $snap->reason);
        $this->assertTrue($snap->is_current);
        $this->assertEquals(50.0, (float) $snap->score);
        $this->assertSame($advisor->id, $snap->computed_by);
        $this->assertSame(['b'], $snap->evaluation['unmet']);
        $this->assertCount(2, $snap->config['criteria']);
        $this->assertSame(1, $snap->rule_set_version);
    }

    public function test_editing_weights_does_not_change_shown_result(): void
    {
        [$enterprise, $advisor, $assessment] = $this->finalizedAssessment();
        $this->bumpWeight('b', 3);

        $this->actingAs($advisor)->get(route('readiness-assessments.show', $assessment))->assertOk()
            ->assertInertia(fn ($p) => $p->where('evaluation.score', 50)->where('snapshot.reason', 'initial')->where('canRecalculate', true));
        $this->actingAs($advisor)->get(route('enterprises.readiness.index', $enterprise))
            ->assertInertia(fn ($p) => $p->where('assessments.0.score', '50.00'));
        $this->assertSame(1, ReadinessAssessmentSnapshot::query()->count());
    }

    public function test_recalculation_creates_new_current_snapshot_and_keeps_old(): void
    {
        [, $advisor, $assessment] = $this->finalizedAssessment();
        $this->bumpWeight('b', 3);

        $this->actingAs($advisor)->post(route('readiness-assessments.recalculate', $assessment), ['reason' => 'أوزان جديدة'])->assertRedirect();

        $snaps = ReadinessAssessmentSnapshot::query()->orderBy('id')->get();
        $this->assertCount(2, $snaps);
        [$old, $new] = [$snaps[0], $snaps[1]];
        $this->assertEquals(50.0, (float) $old->score);
        $this->assertFalse($old->is_current);
        $this->assertEquals(25.0, (float) $new->score);
        $this->assertTrue($new->is_current);
        $this->assertSame('recalculation', $new->reason);
        $this->assertSame('أوزان جديدة', $new->reason_note);
        $this->assertSame($old->id, $new->previous_snapshot_id);
        $this->assertNotSame($old->config_hash, $new->config_hash);
        $this->assertEquals(50.0, (float) $assessment->fresh()->score); // assessment row stays frozen

        $this->actingAs($advisor)->get(route('readiness-assessments.show', $assessment))
            ->assertInertia(fn ($p) => $p->where('evaluation.score', 25)->has('snapshotHistory', 2)->where('snapshotHistory.1.score', '50.00'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'readiness.score_snapshot_recalculation']);
    }

    public function test_snapshot_data_cannot_be_rewritten(): void
    {
        $this->finalizedAssessment();
        $this->expectException(\LogicException::class);
        ReadinessAssessmentSnapshot::query()->sole()->update(['score' => 99]);
    }

    public function test_unauthorized_recalculation_forbidden(): void
    {
        [$enterprise, , $assessment] = $this->finalizedAssessment();
        $sme = $this->smeOf($enterprise);
        $otherAdvisor = $this->user(UserRole::BeaconAdvisor);

        $this->actingAs($sme)->post(route('readiness-assessments.recalculate', $assessment))->assertForbidden();
        $this->actingAs($otherAdvisor)->post(route('readiness-assessments.recalculate', $assessment))->assertForbidden();
        $this->assertSame(1, ReadinessAssessmentSnapshot::query()->count());
    }

    public function test_cross_tenant_access_denied(): void
    {
        [, , $assessment] = $this->finalizedAssessment();
        $other = $this->enterprise('منشأة أخرى');
        $otherSme = $this->smeOf($other);

        $this->actingAs($otherSme)->get(route('readiness-assessments.show', $assessment))->assertForbidden();
        $this->actingAs($otherSme)->post(route('readiness-assessments.recalculate', $assessment))->assertForbidden();
    }

    public function test_draft_cannot_be_recalculated(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $this->publishedRuleSet([['code' => 'a', 'weight' => 1]]);
        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $enterprise));
        $assessment = ReadinessAssessment::query()->firstOrFail();
        $this->actingAs($advisor)->post(route('readiness-assessments.recalculate', $assessment))->assertStatus(422);
        $this->assertSame(0, ReadinessAssessmentSnapshot::query()->count());
    }
}
