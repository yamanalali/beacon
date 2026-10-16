<?php

namespace Tests\Feature\Readiness;

use App\Models\ReadinessAssessmentResult;
use App\Models\ReadinessCriterion;
use App\Models\ReadinessPillar;
use App\Models\ReadinessRuleSet;
use App\Models\ReadinessScoreBand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class ScoringMatrixSchemaTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    private function draftSet(int $version = 1): ReadinessRuleSet
    {
        return ReadinessRuleSet::query()->create(['version_number' => $version, 'name_ar' => 'v'.$version, 'status' => ReadinessRuleSet::STATUS_DRAFT]);
    }

    public function test_migrations_add_v2_tables_and_columns(): void
    {
        $this->assertTrue(Schema::hasTable('readiness_pillars'));
        $this->assertTrue(Schema::hasTable('readiness_score_bands'));
        $this->assertTrue(Schema::hasColumns('readiness_criteria', ['readiness_pillar_id', 'type', 'config', 'sort_order']));
        $this->assertTrue(Schema::hasColumns('readiness_assessment_results', ['value', 'points', 'outcome']));
    }

    public function test_existing_style_criteria_default_to_boolean_without_pillar(): void
    {
        $set = $this->publishedRuleSet([['code' => 'C1', 'weight' => 10]]);
        $criterion = $set->criteria()->first()->fresh();

        $this->assertSame(ReadinessCriterion::TYPE_BOOLEAN, $criterion->type);
        $this->assertNull($criterion->readiness_pillar_id);
        $this->assertNull($criterion->config);
    }

    public function test_relations_and_config_cast(): void
    {
        $set = $this->draftSet();
        $pillar = $set->pillars()->create(['code' => 'fin', 'label_ar' => 'مالي', 'weight' => 40, 'min_score' => 50]);
        $band = $set->bands()->create(['label_ar' => 'جاهز', 'min' => 70, 'max' => 100, 'color_token' => 'success']);
        $config = ['unit' => '%', 'bands' => [['min' => 0, 'max' => 10, 'points' => 2], ['min' => 20, 'max' => null, 'points' => 5]]];
        $criterion = $set->criteria()->create([
            'code' => 'margin', 'label_ar' => 'هامش', 'weight' => 5, 'missing_data_treatment' => 'counts_as_unmet',
            'readiness_pillar_id' => $pillar->id, 'type' => ReadinessCriterion::TYPE_NUMERIC_BANDS, 'config' => $config,
        ]);

        $fresh = $criterion->fresh();
        $this->assertSame($config, $fresh->config);
        $this->assertTrue($fresh->pillar->is($pillar));
        $this->assertTrue($pillar->criteria()->first()->is($criterion));
        $this->assertTrue($set->pillars()->first()->is($pillar));
        $this->assertTrue($set->bands()->first()->is($band));
        $this->assertTrue($band->ruleSet->is($set));
        $this->assertContains('target_ratio', ReadinessCriterion::TYPES);
    }

    public function test_deleting_pillar_keeps_criteria_pillarless(): void
    {
        $set = $this->draftSet();
        $pillar = $set->pillars()->create(['code' => 'ops', 'label_ar' => 'تشغيلي']);
        $criterion = $set->criteria()->create(['code' => 'c', 'label_ar' => 'c', 'missing_data_treatment' => 'excluded', 'readiness_pillar_id' => $pillar->id]);

        $pillar->delete();

        $this->assertNull($criterion->fresh()->readiness_pillar_id);
    }

    public function test_result_value_and_points_casts(): void
    {
        $this->assertSame('array', (new ReadinessAssessmentResult)->getCasts()['value']);
        $this->assertArrayHasKey('points', (new ReadinessAssessmentResult)->getCasts());
    }

    public function test_pillars_and_bands_are_frozen_on_published_sets(): void
    {
        $set = $this->draftSet();
        $pillar = $set->pillars()->create(['code' => 'fin', 'label_ar' => 'مالي']);
        $band = $set->bands()->create(['label_ar' => 'جاهز', 'min' => 0, 'max' => 100, 'color_token' => 'success']);
        $set->update(['status' => ReadinessRuleSet::STATUS_PUBLISHED, 'published_at' => now()]);

        $attempts = [
            fn () => $set->pillars()->create(['code' => 'new', 'label_ar' => 'x']),
            fn () => $pillar->update(['weight' => 10]),
            fn () => $pillar->delete(),
            fn () => $set->bands()->create(['label_ar' => 'x', 'min' => 0, 'max' => 1, 'color_token' => 'danger']),
            fn () => $band->update(['max' => 90]),
            fn () => $band->delete(),
        ];
        foreach ($attempts as $i => $attempt) {
            try {
                $attempt();
                $this->fail("Attempt {$i} should be blocked on a published set.");
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_cannot_move_pillar_from_published_set_into_draft(): void
    {
        $published = $this->draftSet(1);
        $pillar = $published->pillars()->create(['code' => 'fin', 'label_ar' => 'مالي']);
        $published->update(['status' => ReadinessRuleSet::STATUS_PUBLISHED, 'published_at' => now()]);
        $draft = $this->draftSet(2);

        $this->expectException(LogicException::class);
        $pillar->update(['readiness_rule_set_id' => $draft->id]);
    }
}
