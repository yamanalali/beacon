<?php

namespace Tests\Feature\Readiness;

use App\Enums\UserRole;
use App\Models\ReadinessAssessment;
use App\Models\ReadinessCriterion;
use App\Models\ReadinessRuleSet;
use App\Support\ReadinessScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringEngineTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    private function scorer(): ReadinessScorer
    {
        return app(ReadinessScorer::class);
    }

    private function draft(): ReadinessRuleSet
    {
        return ReadinessRuleSet::query()->create(['version_number' => 1, 'name_ar' => 'مسودة', 'status' => ReadinessRuleSet::STATUS_DRAFT]);
    }

    private function criterion(ReadinessRuleSet $set, string $code, float $weight, array $extra = []): ReadinessCriterion
    {
        return $set->criteria()->create($extra + [
            'code' => $code, 'label_ar' => 'معيار '.$code, 'weight' => $weight,
            'missing_data_treatment' => 'counts_as_unmet', 'is_mandatory' => false,
        ]);
    }

    public function test_points_per_criterion_type(): void
    {
        $this->assertSame(1.0, ReadinessScorer::points('boolean', null, true));
        $this->assertSame(0.0, ReadinessScorer::points('boolean', null, false));

        $bands = ['unit' => '%', 'bands' => [['min' => null, 'max' => 10, 'points' => 1], ['min' => 10.01, 'max' => 20, 'points' => 3], ['min' => 20.01, 'max' => null, 'points' => 4]]];
        $this->assertSame(0.75, ReadinessScorer::points('numeric_bands', $bands, 15));
        $this->assertSame(1.0, ReadinessScorer::points('numeric_bands', $bands, 99));
        $this->assertSame(0.25, ReadinessScorer::points('numeric_bands', $bands, -5));

        $choice = ['options' => [['value' => 'a', 'label_ar' => 'أ', 'points' => 2], ['value' => 'b', 'label_ar' => 'ب', 'points' => 4]]];
        $this->assertSame(0.5, ReadinessScorer::points('choice', $choice, 'a'));
        $this->assertSame(1.0, ReadinessScorer::points('choice', $choice, 'b'));
        $this->assertSame(0.0, ReadinessScorer::points('choice', $choice, 'zzz'));

        $higher = ['target' => 100, 'direction' => 'higher', 'cap' => 1];
        $this->assertSame(0.5, ReadinessScorer::points('target_ratio', $higher, 50));
        $this->assertSame(1.0, ReadinessScorer::points('target_ratio', $higher, 150));
        $this->assertSame(0.8, ReadinessScorer::points('target_ratio', ['target' => 100, 'direction' => 'higher', 'cap' => 0.8], 150));
        $this->assertSame(0.5, ReadinessScorer::points('target_ratio', ['target' => 10, 'direction' => 'lower'], 20));
        $this->assertSame(1.0, ReadinessScorer::points('target_ratio', ['target' => 10, 'direction' => 'lower'], 5));

        $this->assertSame(0.75, ReadinessScorer::points('manual_scale', ['min' => 1, 'max' => 5], 4));
        $this->assertSame(0.0, ReadinessScorer::points('manual_scale', ['min' => 1, 'max' => 5], 1));
        $this->assertNull(ReadinessScorer::points('manual_scale', ['min' => 1, 'max' => 5], null));
    }

    public function test_typed_values_feed_the_breakdown(): void
    {
        $set = $this->draft();
        $scale = $this->criterion($set, 'scale', 1, ['type' => 'manual_scale', 'config' => ['min' => 1, 'max' => 5]]);
        $ratio = $this->criterion($set, 'ratio', 1, ['type' => 'target_ratio', 'config' => ['target' => 100, 'direction' => 'higher', 'cap' => 1]]);

        $result = $this->scorer()->evaluateAnswers($set, [$scale->id => ['value' => 4], $ratio->id => ['value' => 50]]);

        $this->assertEquals(62.5, $result['score']);
        $rows = collect($result['pillars'][0]['criteria'])->keyBy('code');
        $this->assertSame(0.75, $rows['scale']['points']);
        $this->assertSame('met', $rows['scale']['outcome']);
        $this->assertEquals(37.5, $rows['scale']['contribution']);
        $this->assertEquals(25.0, $rows['ratio']['contribution']);
        $this->assertSame(ReadinessScorer::IMPLICIT_PILLAR, $result['pillars'][0]['code']);
    }

    public function test_pillar_weighting_min_score_blocking_and_mandatory(): void
    {
        $set = $this->draft();
        $fin = $set->pillars()->create(['code' => 'fin', 'label_ar' => 'مالي', 'weight' => 3, 'sort_order' => 1]);
        $ops = $set->pillars()->create(['code' => 'ops', 'label_ar' => 'تشغيلي', 'weight' => 1, 'min_score' => 50, 'sort_order' => 2]);
        $a = $this->criterion($set, 'a', 1, ['readiness_pillar_id' => $fin->id]);
        $b = $this->criterion($set, 'b', 9, ['readiness_pillar_id' => $ops->id, 'is_mandatory' => true]);

        $result = $this->scorer()->evaluateAnswers($set, [$a->id => ['outcome' => 'met'], $b->id => ['outcome' => 'not_met']]);

        // Pillar weights decide, not criterion weights: (100*3 + 0*1) / 4.
        $this->assertEquals(75.0, $result['score']);
        $this->assertSame(['fin', 'ops'], array_column($result['pillars'], 'code'));
        $this->assertEquals([100.0, 0.0], array_column($result['pillars'], 'score'));
        $this->assertEquals([75.0, 0.0], array_column($result['pillars'], 'contribution'));
        $this->assertSame([false, true], array_column($result['pillars'], 'blocking'));
        $this->assertSame(['ops', 'b'], $result['blocking']);
        $this->assertFalse($result['mandatory_conditions_met']);
        $this->assertSame(['b'], $result['unmet_mandatory']);
        $this->assertSame([['code' => 'b', 'label_ar' => 'معيار b', 'potential_gain' => 25.0]], $result['improvements']);
    }

    public function test_band_selection_and_shared_boundary_goes_up(): void
    {
        $set = $this->draft();
        $set->bands()->create(['label_ar' => 'يحتاج دعم', 'min' => 0, 'max' => 50, 'color_token' => 'danger', 'sort_order' => 1]);
        $set->bands()->create(['label_ar' => 'جاهز', 'min' => 50, 'max' => 100, 'color_token' => 'success', 'sort_order' => 2]);
        $a = $this->criterion($set, 'a', 1);
        $b = $this->criterion($set, 'b', 1);

        $half = $this->scorer()->evaluateAnswers($set, [$a->id => ['outcome' => 'met'], $b->id => ['outcome' => 'not_met']]);
        $this->assertEquals(50.0, $half['score']);
        $this->assertSame(['label_ar' => 'جاهز', 'name' => 'جاهز', 'color_token' => 'success', 'min' => 50.0, 'max' => 100.0], $half['band']);

        $low = $this->scorer()->evaluateAnswers($set, [$a->id => ['outcome' => 'not_met'], $b->id => ['outcome' => 'not_met']]);
        $this->assertSame('danger', $low['band']['color_token']);

        $none = $this->scorer()->evaluateAnswers($this->draftWithout(), []);
        $this->assertNull($none['band']);
    }

    private function draftWithout(): ReadinessRuleSet
    {
        return ReadinessRuleSet::query()->create(['version_number' => 2, 'name_ar' => 'فارغة', 'status' => ReadinessRuleSet::STATUS_DRAFT]);
    }

    public function test_improvements_are_sorted_by_potential_gain(): void
    {
        $set = $this->draft();
        $small = $this->criterion($set, 'small', 1);
        $big = $this->criterion($set, 'big', 5);
        $half = $this->criterion($set, 'half', 4, ['type' => 'manual_scale', 'config' => ['min' => 0, 'max' => 10]]);
        $done = $this->criterion($set, 'done', 10);

        $result = $this->scorer()->evaluateAnswers($set, [
            $small->id => ['outcome' => 'not_met'], $big->id => ['outcome' => 'not_met'],
            $half->id => ['value' => 5], $done->id => ['outcome' => 'met'],
        ]);

        $this->assertSame(['big', 'half', 'small'], array_column($result['improvements'], 'code'));
        $this->assertEquals([25.0, 10.0, 5.0], array_column($result['improvements'], 'potential_gain'));
    }

    public function test_missing_data_treatment_is_kept(): void
    {
        $set = $this->draft();
        $this->criterion($set, 'unmet', 1);
        $this->criterion($set, 'excl', 1, ['missing_data_treatment' => 'excluded']);
        $this->criterion($set, 'rev', 1, ['missing_data_treatment' => 'needs_review', 'is_mandatory' => true]);
        $met = $this->criterion($set, 'met', 1);

        $result = $this->scorer()->evaluateAnswers($set, [$met->id => ['outcome' => 'met']]);

        $this->assertEquals(50.0, $result['score']);
        $this->assertSame(['unmet', 'excl', 'rev'], $result['missing']);
        $this->assertSame(['rev'], $result['needs_review']);
        $this->assertNull($result['mandatory_conditions_met']);
        $rows = collect($result['pillars'][0]['criteria'])->keyBy('code');
        $this->assertNull($rows['excl']['points']);
        $this->assertSame('missing', $rows['excl']['outcome']);
    }

    /** Legacy rule sets (boolean, no pillars) must keep exactly the v1 score and keys. */
    public function test_legacy_score_is_unchanged(): void
    {
        mt_srand(25);
        $treatments = array_keys(ReadinessCriterion::MISSING_DATA_TREATMENTS);
        for ($round = 0; $round < 40; $round++) {
            ReadinessRuleSet::query()->delete();
            $set = $this->draft();
            $answers = [];
            $v1 = ['earned' => 0.0, 'counted' => 0.0, 'unmet' => [], 'mandatory' => [], 'review' => [], 'missing' => [], 'reviewMandatory' => false];
            for ($i = 0; $i < mt_rand(1, 7); $i++) {
                $weight = mt_rand(0, 2000) / 100;
                $treatment = $treatments[mt_rand(0, 2)];
                $mandatory = (bool) mt_rand(0, 1);
                $c = $this->criterion($set, 'c'.$i, $weight, ['missing_data_treatment' => $treatment, 'is_mandatory' => $mandatory]);
                $outcome = ['met', 'not_met', 'missing', 'needs_review'][mt_rand(0, 3)];
                $answers[$c->id] = ['outcome' => $outcome];

                // v1 reference implementation (pre-matrix ReadinessScorer).
                if ($outcome === 'missing') {
                    $v1['missing'][] = $c->code;
                    $outcome = ['counts_as_unmet' => 'not_met', 'excluded' => 'excluded', 'needs_review' => 'needs_review'][$treatment];
                }
                $w = (float) $c->fresh()->weight;
                if ($outcome === 'met') {
                    $v1['earned'] += $w;
                    $v1['counted'] += $w;
                } elseif ($outcome === 'not_met') {
                    $v1['counted'] += $w;
                    $v1['unmet'][] = $c->code;
                    if ($mandatory) {
                        $v1['mandatory'][] = $c->code;
                    }
                } elseif ($outcome === 'needs_review') {
                    $v1['review'][] = $c->code;
                    $v1['reviewMandatory'] = $v1['reviewMandatory'] || $mandatory;
                }
            }

            $result = $this->scorer()->evaluateAnswers($set->fresh(), $answers);

            $this->assertSame($v1['counted'] > 0 ? round($v1['earned'] / $v1['counted'] * 100, 2) : null, $result['score'], "round $round");
            $this->assertSame($v1['mandatory'] !== [] ? false : ($v1['reviewMandatory'] ? null : true), $result['mandatory_conditions_met']);
            $this->assertEquals($v1['earned'], $result['earned_weight']);
            $this->assertEquals($v1['counted'], $result['counted_weight']);
            $this->assertSame($v1['unmet'], $result['unmet']);
            $this->assertSame($v1['mandatory'], $result['unmet_mandatory']);
            $this->assertSame($v1['review'], $result['needs_review']);
            $this->assertSame($v1['missing'], $result['missing']);
        }
    }

    public function test_evaluate_on_assessment_keeps_v1_keys_and_adds_v2_keys(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $this->publishedRuleSet([['code' => 'a', 'weight' => 3], ['code' => 'b', 'weight' => 1]]);
        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $enterprise))->assertRedirect();
        $assessment = ReadinessAssessment::query()->firstOrFail();
        $assessment->results()->whereHas('criterion', fn ($q) => $q->where('code', 'a'))->update(['outcome' => 'met']);
        $assessment->results()->whereHas('criterion', fn ($q) => $q->where('code', 'b'))->update(['outcome' => 'not_met']);

        $result = $this->scorer()->evaluate($assessment->fresh());

        foreach (['score', 'mandatory_conditions_met', 'earned_weight', 'counted_weight', 'unmet_mandatory', 'needs_review', 'missing', 'unmet', 'band', 'blocking', 'pillars', 'breakdown', 'improvements'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
        $this->assertEquals(75.0, $result['score']);
        $this->assertSame(['code', 'label_ar', 'type', 'value', 'points', 'weight', 'outcome', 'contribution'], array_keys($result['pillars'][0]['criteria'][0]));
    }

    public function test_answers_are_evaluated_without_writes(): void
    {
        $set = $this->draft();
        $a = $this->criterion($set, 'a', 1);
        $this->user(UserRole::BeaconAdmin);

        \DB::enableQueryLog();
        $this->scorer()->evaluateAnswers($set->fresh(), [$a->id => ['outcome' => 'met']]);
        $writes = collect(\DB::getQueryLog())->filter(fn ($q) => preg_match('/^\s*(insert|update|delete)/i', $q['query']));
        $this->assertCount(0, $writes);
    }

    public function test_finalized_snapshot_stores_breakdown_and_band_color(): void
    {
        $enterprise = $this->enterprise();
        $advisor = $this->advisorOf($enterprise);
        $set = $this->draft();
        $fin = $set->pillars()->create(['code' => 'fin', 'label_ar' => 'مالي', 'weight' => 1]);
        $this->criterion($set, 'a', 1, ['readiness_pillar_id' => $fin->id]);
        $set->bands()->create(['label_ar' => 'ضعيف', 'min' => 0, 'max' => 49.99, 'color_token' => 'danger']);
        $set->bands()->create(['label_ar' => 'جاهز', 'min' => 50, 'max' => 100, 'color_token' => 'success']);
        $set->update(['status' => ReadinessRuleSet::STATUS_PUBLISHED, 'published_at' => now()]);

        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $enterprise))->assertRedirect();
        $assessment = ReadinessAssessment::query()->firstOrFail();
        $result = $assessment->results()->firstOrFail();
        $this->actingAs($advisor)->put(route('readiness-assessments.results.update', [$assessment, $result]), ['outcome' => 'met'])->assertRedirect();
        $this->actingAs($advisor)->post(route('readiness-assessments.finalize', $assessment))->assertRedirect();

        $row = \DB::table('readiness_assessment_snapshots')->where('readiness_assessment_id', $assessment->id)->first();
        $evaluation = json_decode($row->evaluation, true);
        $this->assertSame('success', $evaluation['band']['color_token']);
        $this->assertSame('fin', $evaluation['breakdown'][0]['code']);
        $this->assertSame($evaluation['pillars'], $evaluation['breakdown']);
        $this->assertEquals(100.0, $evaluation['breakdown'][0]['score']);
        $this->assertSame('جاهز', $row->band);
    }
}
