<?php

namespace Tests\Feature\Readiness;

use App\Models\ReadinessAssessment;
use App\Models\ReadinessAssessmentSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SnapshotResultPropsTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReadiness();
    }

    /** @return array{0:\App\Models\Enterprise,1:\App\Models\User,2:ReadinessAssessment} */
    private function assessment(bool $finalize = true): array
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $set = $this->publishedRuleSet([['code' => 'a', 'weight' => 1], ['code' => 'b', 'weight' => 1]]);
        $pillarId = DB::table('readiness_pillars')->insertGetId(['readiness_rule_set_id' => $set->id, 'code' => 'fin', 'label_ar' => 'مالي', 'weight' => 1, 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('readiness_criteria')->where('readiness_rule_set_id', $set->id)->update(['readiness_pillar_id' => $pillarId]);
        foreach ([['ضعيف', 0, 40, 'danger', 1], ['جاهز', 40.01, 100, 'success', 2]] as [$l, $min, $max, $c, $o]) {
            DB::table('readiness_score_bands')->insert(['readiness_rule_set_id' => $set->id, 'label_ar' => $l, 'min' => $min, 'max' => $max, 'color_token' => $c, 'sort_order' => $o, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $enterprise))->assertRedirect();
        $assessment = ReadinessAssessment::query()->firstOrFail();
        $a = $assessment->results()->whereHas('criterion', fn ($q) => $q->where('code', 'a'))->firstOrFail();
        $this->actingAs($advisor)->put(route('readiness-assessments.results.update', [$assessment, $a]), ['outcome' => 'met']);
        if ($finalize) {
            $this->actingAs($advisor)->post(route('readiness-assessments.finalize', $assessment))->assertRedirect();
        }

        return [$enterprise, $advisor, $assessment->fresh()];
    }

    public function test_new_snapshot_exposes_full_result_in_props(): void
    {
        [, $advisor, $assessment] = $this->assessment();

        $this->actingAs($advisor)->get(route('readiness-assessments.show', $assessment))->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('snapshot.result.score', 50)
                ->where('snapshot.result.band.color_token', 'success')
                ->where('snapshot.result.band.label_ar', 'جاهز')
                ->where('snapshot.band_detail.color_token', 'success')
                ->where('snapshot.result.pillars.0.code', 'fin')
                ->has('snapshot.result.pillars.0.criteria', 2)
                ->where('snapshot.result.breakdown.0.code', 'fin')
                ->has('snapshot.result.improvements')
                ->has('snapshot.result.blocking')
                ->missing('snapshot.result.pillars.0.sort')
                ->missing('snapshot.result.band.id')
                ->where('result.band.color_token', 'success')
                ->where('snapshotHistory.0.band_detail.color_token', 'success')
                ->missing('snapshotHistory.0.result'));
    }

    public function test_old_snapshot_without_v2_keys_renders_with_nulls(): void
    {
        [, $advisor, $assessment] = $this->assessment();
        $legacy = ['score' => 50.0, 'mandatory_conditions_met' => true, 'earned_weight' => 1, 'counted_weight' => 2, 'unmet_mandatory' => [], 'needs_review' => [], 'missing' => [], 'unmet' => ['b']];
        DB::table('readiness_assessment_snapshots')->update(['evaluation' => json_encode($legacy), 'band' => null]);

        $this->actingAs($advisor)->get(route('readiness-assessments.show', $assessment))->assertOk()
            ->assertInertia(fn ($p) => $p
                ->where('snapshot.result.score', 50)
                ->where('snapshot.result.band', null)
                ->where('snapshot.result.pillars', null)
                ->where('snapshot.result.breakdown', null)
                ->where('snapshot.result.improvements', null)
                ->where('snapshot.result.blocking', null)
                ->where('snapshot.band_detail', null)
                ->where('snapshot.result.unmet', ['b']));
    }

    public function test_weight_change_after_finalize_does_not_change_props_until_recalculation(): void
    {
        [, $advisor, $assessment] = $this->assessment();
        DB::table('readiness_criteria')->where('code', 'b')->update(['weight' => 3]);

        $this->actingAs($advisor)->get(route('readiness-assessments.show', $assessment))
            ->assertInertia(fn ($p) => $p->where('snapshot.result.score', 50)->where('snapshot.result.band.color_token', 'success')
                ->where('snapshot.result.pillars.0.criteria.1.weight', 1));

        $this->actingAs($advisor)->post(route('readiness-assessments.recalculate', $assessment), ['reason' => 'x'])->assertRedirect();

        $this->actingAs($advisor)->get(route('readiness-assessments.show', $assessment))
            ->assertInertia(fn ($p) => $p->where('snapshot.result.score', 25)->where('snapshot.result.band.color_token', 'danger')
                ->where('snapshot.result.pillars.0.criteria.1.weight', 3)
                ->has('snapshotHistory', 2)
                ->where('snapshotHistory.0.band_detail.color_token', 'danger')
                ->where('snapshotHistory.1.band_detail.color_token', 'success'));
    }

    public function test_draft_exposes_live_result_in_same_shape(): void
    {
        [, $advisor, $assessment] = $this->assessment(false);

        $this->actingAs($advisor)->get(route('readiness-assessments.show', $assessment))
            ->assertInertia(fn ($p) => $p->where('snapshot', null)->where('result.score', 50)
                ->where('result.band.color_token', 'success')->where('result.pillars.0.code', 'fin')->has('snapshotHistory', 0));
    }

    public function test_other_tenant_cannot_view(): void
    {
        [, , $assessment] = $this->assessment();
        $outsider = $this->advisorOf($this->enterprise());

        $this->actingAs($outsider)->get(route('readiness-assessments.show', $assessment))->assertForbidden();
    }
}
