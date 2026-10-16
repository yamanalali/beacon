<?php

namespace Tests\Feature\Readiness;

use App\Enums\UserRole;
use App\Models\ReadinessPillar;
use App\Models\ReadinessRuleSet;
use App\Models\User;
use App\Support\ReadinessScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PillarWeightNullableTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReadiness();
        $this->admin = $this->user(UserRole::BeaconAdmin);
    }

    private function draft(int $version = 1): ReadinessRuleSet
    {
        return ReadinessRuleSet::query()->create(['version_number' => $version, 'name_ar' => 'Draft '.$version, 'status' => ReadinessRuleSet::STATUS_DRAFT]);
    }

    public function test_pillar_can_be_created_and_updated_with_null_weight(): void
    {
        $set = $this->draft();
        $this->actingAs($this->admin)->post(route('admin.readiness.pillars.store', $set), ['code' => 'fin', 'label_ar' => 'fin', 'weight' => null])
            ->assertSessionHasNoErrors();
        $pillar = ReadinessPillar::query()->where('code', 'fin')->firstOrFail();
        $this->assertNull($pillar->weight);

        $this->actingAs($this->admin)->put(route('admin.readiness.pillars.update', $pillar), ['weight' => 25])->assertSessionHasNoErrors();
        $this->assertEquals(25, (float) $pillar->fresh()->weight);

        $this->actingAs($this->admin)->put(route('admin.readiness.pillars.update', $pillar), ['weight' => null])->assertSessionHasNoErrors();
        $this->assertNull($pillar->fresh()->weight);

        $this->actingAs($this->admin)->put(route('admin.readiness.pillars.update', $pillar), ['weight' => -1])->assertSessionHasErrors('weight');
        $this->actingAs($this->admin)->put(route('admin.readiness.pillars.update', $pillar), ['weight' => 'abc'])->assertSessionHasErrors('weight');
    }

    public function test_null_weight_scores_like_an_equal_share(): void
    {
        $scorer = app(ReadinessScorer::class);
        $answers = fn ($a, $b) => [$a->id => ['outcome' => 'met'], $b->id => ['outcome' => 'not_met']];
        $criterion = fn (ReadinessRuleSet $set, string $code, float $w, int $pillarId) => $set->criteria()->create([
            'code' => $code, 'label_ar' => $code, 'weight' => $w, 'missing_data_treatment' => 'counts_as_unmet',
            'is_mandatory' => false, 'readiness_pillar_id' => $pillarId,
        ]);

        // All pillars null: equal share, same as all 0.
        $set = $this->draft();
        $fin = $set->pillars()->create(['code' => 'fin', 'label_ar' => 'fin', 'weight' => null, 'sort_order' => 1]);
        $ops = $set->pillars()->create(['code' => 'ops', 'label_ar' => 'ops', 'weight' => null, 'sort_order' => 2]);
        $a = $criterion($set, 'a', 1, $fin->id);
        $b = $criterion($set, 'b', 9, $ops->id);
        $nullResult = $scorer->evaluateAnswers($set->fresh(), $answers($a, $b));
        $this->assertEquals(50.0, $nullResult['score']);

        $fin->update(['weight' => 0]);
        $ops->update(['weight' => 0]);
        $this->assertEquals($nullResult['score'], $scorer->evaluateAnswers($set->fresh(), $answers($a, $b))['score']);

        // One weighted, one null: the null pillar gets the average admin weight, same as 0.
        $fin->update(['weight' => 3]);
        $ops->update(['weight' => null]);
        $this->assertEquals(50.0, $scorer->evaluateAnswers($set->fresh(), $answers($a, $b))['score']);
    }

    public function test_export_and_import_keep_null_weight(): void
    {
        $set = $this->draft();
        $set->pillars()->create(['code' => 'fin', 'label_ar' => 'fin', 'weight' => null, 'sort_order' => 1]);
        $set->criteria()->create(['code' => 'a', 'label_ar' => 'a', 'weight' => 1, 'missing_data_treatment' => 'counts_as_unmet',
            'is_mandatory' => false, 'readiness_pillar_id' => $set->pillars()->first()->id]);
        $set->bands()->create(['label_ar' => 'all', 'min' => 0, 'max' => 100, 'color_token' => 'success', 'sort_order' => 1]);

        $definition = $this->actingAs($this->admin)->get(route('admin.readiness.export', $set))->assertOk()->json();
        $this->assertArrayHasKey('weight', $definition['pillars'][0]);
        $this->assertNull($definition['pillars'][0]['weight']);

        ReadinessRuleSet::query()->whereKey($set->id)->update(['status' => ReadinessRuleSet::STATUS_PUBLISHED]);
        $this->actingAs($this->admin)->post(route('admin.readiness.import'), ['definition' => $definition])->assertSessionHasNoErrors();

        $imported = ReadinessRuleSet::query()->where('status', ReadinessRuleSet::STATUS_DRAFT)->firstOrFail();
        $this->assertNull($imported->pillars()->firstOrFail()->weight);
    }
}