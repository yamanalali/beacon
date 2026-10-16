<?php

namespace Tests\Feature\Readiness;

use App\Enums\UserRole;
use App\Models\ReadinessCriterion;
use App\Models\ReadinessPillar;
use App\Models\ReadinessRuleSet;
use App\Models\ReadinessScoreBand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ScoringMatrixAdminTest extends TestCase
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
        return ReadinessRuleSet::query()->create(['version_number' => $version, 'name_ar' => 'قواعد '.$version, 'status' => ReadinessRuleSet::STATUS_DRAFT]);
    }

    /** A complete, publishable draft with two pillars, typed criteria and bands. */
    private function fullDraft(int $version = 1): ReadinessRuleSet
    {
        $set = $this->draft($version);
        $fin = $set->pillars()->create(['code' => 'fin', 'label_ar' => 'مالي', 'weight' => 60, 'min_score' => 40, 'sort_order' => 1]);
        $ops = $set->pillars()->create(['code' => 'ops', 'label_ar' => 'تشغيلي', 'weight' => 40, 'sort_order' => 2]);
        $set->criteria()->create(['code' => 'books', 'label_ar' => 'دفاتر', 'weight' => 2, 'missing_data_treatment' => 'counts_as_unmet', 'readiness_pillar_id' => $fin->id, 'is_mandatory' => true, 'sort_order' => 1]);
        $set->criteria()->create(['code' => 'margin', 'label_ar' => 'هامش', 'weight' => 3, 'missing_data_treatment' => 'excluded', 'readiness_pillar_id' => $fin->id, 'sort_order' => 2,
            'type' => 'numeric_bands', 'config' => ['unit' => '%', 'bands' => [['min' => null, 'max' => 10, 'points' => 1], ['min' => 10.01, 'max' => null, 'points' => 2]]]]);
        $set->criteria()->create(['code' => 'staff', 'label_ar' => 'فريق', 'weight' => 1, 'missing_data_treatment' => 'needs_review', 'readiness_pillar_id' => $ops->id, 'sort_order' => 3,
            'type' => 'choice', 'config' => ['options' => [['value' => 'small', 'label_ar' => 'صغير', 'points' => 1], ['value' => 'big', 'label_ar' => 'كبير', 'points' => 2]]]]);
        $set->bands()->create(['label_ar' => 'يحتاج دعم', 'min' => 0, 'max' => 50, 'color_token' => 'danger', 'sort_order' => 1]);
        $set->bands()->create(['label_ar' => 'جاهز', 'min' => 50, 'max' => 100, 'color_token' => 'success', 'sort_order' => 2]);

        return $set->fresh();
    }

    public function test_non_admins_are_forbidden(): void
    {
        $set = $this->fullDraft();
        $pillar = $set->pillars()->first();
        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $user = $this->user($role);
            $this->actingAs($user)->post(route('admin.readiness.pillars.store', $set), ['code' => 'x', 'label_ar' => 'x', 'weight' => 1])->assertForbidden();
            $this->actingAs($user)->put(route('admin.readiness.pillars.update', $pillar), ['weight' => 5])->assertForbidden();
            $this->actingAs($user)->delete(route('admin.readiness.pillars.destroy', $pillar))->assertForbidden();
            $this->actingAs($user)->post(route('admin.readiness.bands.store', $set), ['label_ar' => 'x', 'min' => 0, 'max' => 1, 'color_token' => 'danger'])->assertForbidden();
            $this->actingAs($user)->post(route('admin.readiness.duplicate', $set))->assertForbidden();
            $this->actingAs($user)->get(route('admin.readiness.export', $set))->assertForbidden();
            $this->actingAs($user)->post(route('admin.readiness.import'), ['definition' => ['format' => 'x']])->assertForbidden();
            $this->actingAs($user)->postJson(route('admin.readiness.preview', $set), ['answers' => []])->assertForbidden();
            $this->actingAs($user)->post(route('admin.readiness.criteria.reorder', $set), ['ids' => [1]])->assertForbidden();
        }
        $this->assertSame(2, $set->pillars()->count());
    }

    public function test_admin_crud_and_reorder_on_draft(): void
    {
        $set = $this->draft();
        $this->actingAs($this->admin)->post(route('admin.readiness.pillars.store', $set), ['code' => 'fin', 'label_ar' => 'مالي', 'weight' => 50, 'min_score' => 40])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.readiness.pillars.store', $set), ['code' => 'ops', 'label_ar' => 'تشغيلي', 'weight' => 50])->assertSessionHasNoErrors();
        [$fin, $ops] = $set->pillars()->get()->all();

        $this->actingAs($this->admin)->post(route('admin.readiness.criteria.store', $set), [
            'code' => 'scale', 'label_ar' => 'تقييم', 'weight' => 2, 'is_mandatory' => false, 'missing_data_treatment' => 'excluded',
            'type' => 'manual_scale', 'config' => ['min' => 1, 'max' => 5], 'readiness_pillar_id' => $fin->id,
        ])->assertSessionHasNoErrors();
        $criterion = ReadinessCriterion::query()->where('code', 'scale')->firstOrFail();
        $this->assertSame($fin->id, $criterion->readiness_pillar_id);

        $this->actingAs($this->admin)->put(route('admin.readiness.criteria.update', $criterion), ['readiness_pillar_id' => $ops->id, 'weight' => 4])->assertSessionHasNoErrors();
        $this->assertSame($ops->id, $criterion->fresh()->readiness_pillar_id);
        $this->assertEquals(4, $criterion->fresh()->weight);

        $this->actingAs($this->admin)->put(route('admin.readiness.criteria.update', $criterion), ['type' => 'target_ratio', 'config' => ['target' => 0, 'direction' => 'up']])->assertSessionHasErrors('config');
        $this->actingAs($this->admin)->put(route('admin.readiness.pillars.update', $fin), ['code' => '_default'])->assertSessionHasErrors('code');
        $this->actingAs($this->admin)->put(route('admin.readiness.pillars.update', $fin), ['weight' => 70])->assertSessionHasNoErrors();
        $this->assertEquals(70, $fin->fresh()->weight);

        $this->actingAs($this->admin)->post(route('admin.readiness.bands.store', $set), ['label_ar' => 'جاهز', 'min' => 60, 'max' => 50, 'color_token' => 'success'])->assertSessionHasErrors('min');
        $this->actingAs($this->admin)->post(route('admin.readiness.bands.store', $set), ['label_ar' => 'ضعيف', 'min' => 0, 'max' => 50, 'color_token' => 'danger'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('admin.readiness.bands.store', $set), ['label_ar' => 'جاهز', 'min' => 50, 'max' => 100, 'color_token' => 'success'])->assertSessionHasNoErrors();
        [$low, $high] = $set->bands()->get()->all();
        $this->actingAs($this->admin)->put(route('admin.readiness.bands.update', $high), ['label_ar' => 'جاهز تماماً'])->assertSessionHasNoErrors();
        $this->assertSame('جاهز تماماً', $high->fresh()->label_ar);

        $this->actingAs($this->admin)->post(route('admin.readiness.pillars.reorder', $set), ['ids' => [$ops->id, $fin->id]])->assertSessionHasNoErrors();
        $this->assertSame(['ops', 'fin'], $set->pillars()->pluck('code')->all());
        $this->actingAs($this->admin)->post(route('admin.readiness.bands.reorder', $set), ['ids' => [$high->id, $low->id]])->assertSessionHasNoErrors();
        $this->assertSame([$high->id, $low->id], $set->bands()->reorder()->orderBy('sort_order')->pluck('id')->all());

        $this->actingAs($this->admin)->delete(route('admin.readiness.bands.destroy', $low))->assertRedirect();
        $this->actingAs($this->admin)->delete(route('admin.readiness.pillars.destroy', $ops))->assertRedirect();
        $this->assertNull($criterion->fresh()->readiness_pillar_id);
        $this->assertDatabaseCount('readiness_score_bands', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'readiness.pillar_added']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'readiness.criterion_updated']);
    }

    public function test_ids_from_another_rule_set_are_rejected(): void
    {
        $set = $this->fullDraft(2);
        $other = $this->publishedRuleSet([['code' => 'x', 'weight' => 1]], 1);
        $otherCriterion = $other->criteria()->first();
        $otherPillar = ReadinessPillar::query()->create(['readiness_rule_set_id' => $this->draft(3)->id, 'code' => 'foreign', 'label_ar' => 'غريب', 'weight' => 1]);

        $this->actingAs($this->admin)->post(route('admin.readiness.criteria.store', $set), [
            'code' => 'new', 'label_ar' => 'جديد', 'weight' => 1, 'is_mandatory' => false, 'missing_data_treatment' => 'excluded',
            'readiness_pillar_id' => $otherPillar->id,
        ])->assertSessionHasErrors('readiness_pillar_id');
        $this->actingAs($this->admin)->put(route('admin.readiness.criteria.update', $set->criteria()->first()), ['readiness_pillar_id' => $otherPillar->id])->assertSessionHasErrors('readiness_pillar_id');
        $this->actingAs($this->admin)->post(route('admin.readiness.pillars.store', $set), ['code' => 'x', 'label_ar' => 'x', 'weight' => 1, 'readiness_rule_set_id' => $other->id])->assertSessionHasErrors('readiness_rule_set_id');

        $ids = $set->criteria()->pluck('id')->all();
        $this->actingAs($this->admin)->post(route('admin.readiness.criteria.reorder', $set), ['ids' => [...$ids, $otherCriterion->id]])->assertSessionHasErrors('ids');
        $this->actingAs($this->admin)->post(route('admin.readiness.pillars.reorder', $set), ['ids' => [$otherPillar->id]])->assertSessionHasErrors('ids');

        $this->actingAs($this->admin)->postJson(route('admin.readiness.preview', $set), ['answers' => [['criterion_id' => $otherCriterion->id, 'outcome' => 'met']]])
            ->assertStatus(422)->assertJsonValidationErrors('answers.0.criterion_id');

        $definition = $this->actingAs($this->admin)->get(route('admin.readiness.export', $set))->json();
        $definition['criteria'][0]['pillar_id'] = $otherPillar->id;
        $definition['criteria'][1]['pillar_code'] = 'foreign';
        ReadinessRuleSet::query()->whereKey($set->id)->update(['status' => ReadinessRuleSet::STATUS_PUBLISHED]);
        $this->actingAs($this->admin)->post(route('admin.readiness.import'), ['definition' => $definition])
            ->assertSessionHasErrors(['definition.criteria.0.pillar_id', 'definition.criteria.1.pillar_code']);
        $this->assertSame(1, ReadinessRuleSet::query()->where('name_ar', 'قواعد 2')->count());
    }

    public function test_published_sets_are_locked(): void
    {
        $set = $this->fullDraft();
        $set->update(['status' => ReadinessRuleSet::STATUS_PUBLISHED, 'published_at' => now()]);
        $pillar = $set->pillars()->first();
        $band = $set->bands()->first();
        $criterion = $set->criteria()->first();

        $this->actingAs($this->admin)->post(route('admin.readiness.pillars.store', $set), ['code' => 'new', 'label_ar' => 'x', 'weight' => 1])->assertStatus(422);
        $this->actingAs($this->admin)->put(route('admin.readiness.pillars.update', $pillar), ['weight' => 1])->assertStatus(422);
        $this->actingAs($this->admin)->delete(route('admin.readiness.pillars.destroy', $pillar))->assertStatus(422);
        $this->actingAs($this->admin)->put(route('admin.readiness.criteria.update', $criterion), ['weight' => 9])->assertStatus(422);
        $this->actingAs($this->admin)->post(route('admin.readiness.criteria.reorder', $set), ['ids' => $set->criteria()->pluck('id')->all()])->assertStatus(422);
        $this->actingAs($this->admin)->post(route('admin.readiness.bands.store', $set), ['label_ar' => 'x', 'min' => 0, 'max' => 1, 'color_token' => 'danger'])->assertStatus(422);
        $this->actingAs($this->admin)->put(route('admin.readiness.bands.update', $band), ['max' => 40])->assertStatus(422);
        $this->actingAs($this->admin)->delete(route('admin.readiness.bands.destroy', $band))->assertStatus(422);

        $this->assertEquals(60, $pillar->fresh()->weight);
        $this->assertEquals(2, $criterion->fresh()->weight);
        $this->assertDatabaseCount('readiness_score_bands', 2);
    }

    public function test_duplicate_creates_a_draft_with_remapped_ids(): void
    {
        $source = $this->fullDraft();
        $source->update(['status' => ReadinessRuleSet::STATUS_PUBLISHED, 'published_at' => now()]);

        $this->actingAs($this->admin)->post(route('admin.readiness.duplicate', $source))->assertRedirect();
        $copy = ReadinessRuleSet::query()->where('status', ReadinessRuleSet::STATUS_DRAFT)->firstOrFail();

        $this->assertSame(2, $copy->version_number);
        $this->assertSame(['fin', 'ops'], $copy->pillars()->pluck('code')->all());
        $this->assertCount(2, $copy->bands);
        $copyPillarIds = $copy->pillars()->pluck('id', 'code');
        foreach ($copy->criteria as $criterion) {
            $this->assertContains($criterion->readiness_pillar_id, $copyPillarIds->all());
        }
        $this->assertSame($copyPillarIds['ops'], $copy->criteria()->where('code', 'staff')->value('readiness_pillar_id'));
        $this->assertEquals($source->criteria()->where('code', 'margin')->first()->config, $copy->criteria()->where('code', 'margin')->first()->config);
        $this->assertSame(2, $source->pillars()->count());

        // Only one draft at a time (same rule as creating a draft).
        $this->actingAs($this->admin)->post(route('admin.readiness.duplicate', $source))->assertStatus(422);
    }

    public function test_export_import_roundtrip_always_creates_a_draft(): void
    {
        $source = $this->fullDraft();
        $source->update(['status' => ReadinessRuleSet::STATUS_PUBLISHED, 'published_at' => now()]);

        $export = $this->actingAs($this->admin)->get(route('admin.readiness.export', $source))->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="readiness-rules-v1.json"')->json();
        $this->assertSame('beacon.readiness.rule_set', $export['format']);
        $this->assertStringNotContainsString('"id"', json_encode($export));

        $export['status'] = 'published';
        $this->actingAs($this->admin)->post(route('admin.readiness.import'), ['definition' => $export])->assertRedirect()->assertSessionHasNoErrors();
        $imported = ReadinessRuleSet::query()->where('version_number', 2)->firstOrFail();
        $this->assertSame(ReadinessRuleSet::STATUS_DRAFT, $imported->status);

        $again = $this->actingAs($this->admin)->get(route('admin.readiness.export', $imported))->json();
        foreach (['pillars', 'criteria', 'bands'] as $key) {
            $this->assertEquals($export[$key], $again[$key], $key);
        }
    }

    public function test_import_accepts_an_uploaded_json_file_and_validates_it(): void
    {
        $source = $this->fullDraft();
        $definition = $this->actingAs($this->admin)->get(route('admin.readiness.export', $source))->json();
        $source->update(['status' => ReadinessRuleSet::STATUS_PUBLISHED, 'published_at' => now()]);

        $bad = $definition;
        $bad['bands'][1]['min'] = 60; // gap 50..60
        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('rules.json', json_encode($bad));
        $this->actingAs($this->admin)->post(route('admin.readiness.import'), ['file' => $file])->assertSessionHasErrors('definition.bands');
        $this->assertSame(1, ReadinessRuleSet::query()->count());

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('rules.json', json_encode($definition));
        $this->actingAs($this->admin)->post(route('admin.readiness.import'), ['file' => $file, 'name_ar' => 'مستوردة'])->assertSessionHasNoErrors();
        $this->assertSame('مستوردة', ReadinessRuleSet::query()->where('status', 'draft')->value('name_ar'));

        $this->actingAs($this->admin)->post(route('admin.readiness.import'), ['definition' => ['format' => 'other']])->assertSessionHasErrors('definition');
    }

    public function test_publish_runs_the_matrix_validation(): void
    {
        $set = $this->fullDraft();
        $set->bands()->where('min', 50)->update(['min' => 55]);
        $this->actingAs($this->admin)->post(route('admin.readiness.publish', $set))->assertSessionHasErrors('bands');
        $this->assertSame('draft', $set->fresh()->status);

        $set->bands()->where('min', 55)->update(['min' => 40]);
        $this->actingAs($this->admin)->post(route('admin.readiness.publish', $set))->assertSessionHasErrors('bands'); // overlap

        $set->bands()->where('min', 40)->update(['min' => 50.01]);
        $set->pillars()->where('code', 'ops')->update(['weight' => -5]); // 0 = equal default weight, negative is invalid
        $set->criteria()->where('code', 'books')->update(['weight' => 0]);
        $set->criteria()->where('code', 'margin')->update(['config' => json_encode(['bands' => [['min' => 0, 'max' => 20, 'points' => 1], ['min' => 10, 'max' => 30, 'points' => 2]]])]);
        $this->actingAs($this->admin)->post(route('admin.readiness.publish', $set))
            ->assertSessionHasErrors(['pillars.1.weight', 'criteria.0.weight', 'criteria.1.config']);

        $set->pillars()->where('code', 'ops')->update(['weight' => 40]);
        $set->criteria()->where('code', 'books')->update(['weight' => 2]);
        $set->criteria()->where('code', 'margin')->update(['config' => json_encode(['bands' => [['min' => 0, 'max' => 20, 'points' => 1], ['min' => 20.5, 'max' => 30, 'points' => 2]]])]);
        $this->actingAs($this->admin)->post(route('admin.readiness.publish', $set))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('published', $set->fresh()->status);
    }

    public function test_publish_without_bands_is_forbidden_but_preview_and_import_allow_it(): void
    {
        $source = $this->fullDraft();
        $export = $this->actingAs($this->admin)->get(route('admin.readiness.export', $source))->json();
        $source->bands()->delete();

        $this->actingAs($this->admin)->post(route('admin.readiness.publish', $source))->assertSessionHasErrors('bands');
        $this->assertSame('draft', $source->fresh()->status);
        $errors = session('errors')->get('bands');
        $this->assertSame([json_decode('"\u0644\u0627 \u064a\u0645\u0643\u0646 \u0646\u0634\u0631 \u0627\u0644\u0642\u0648\u0627\u0639\u062f \u0628\u062f\u0648\u0646 \u0646\u0637\u0627\u0642\u0627\u062a \u062a\u0635\u0646\u064a\u0641."')], $errors);

        $ids = $source->criteria()->pluck('id', 'code');
        $this->actingAs($this->admin)->postJson(route('admin.readiness.preview', $source), ['answers' => [
            ['criterion_id' => $ids['books'], 'outcome' => 'met'],
        ]])->assertOk()->assertJsonPath('evaluation.band', null);

        $export['bands'] = [];
        $source->update(['status' => ReadinessRuleSet::STATUS_RETIRED]); // only one draft may exist
        $this->actingAs($this->admin)->post(route('admin.readiness.import'), ['definition' => $export])->assertRedirect()->assertSessionHasNoErrors();
        $imported = ReadinessRuleSet::query()->where('version_number', 2)->firstOrFail();
        $this->assertSame(ReadinessRuleSet::STATUS_DRAFT, $imported->status);
        $this->assertSame(0, $imported->bands()->count());
    }

    public function test_preview_evaluates_without_writes(): void
    {
        $set = $this->fullDraft();
        $ids = $set->criteria()->pluck('id', 'code');
        $counts = fn () => [DB::table('readiness_rule_sets')->count(), DB::table('readiness_assessments')->count(), DB::table('readiness_assessment_results')->count(), DB::table('audit_logs')->count(), DB::table('readiness_assessment_snapshots')->count()];
        $before = $counts();

        $response = $this->actingAs($this->admin)->postJson(route('admin.readiness.preview', $set), ['answers' => [
            ['criterion_id' => $ids['books'], 'outcome' => 'met'],
            ['criterion_id' => $ids['margin'], 'value' => 5],
            ['criterion_id' => $ids['staff'], 'value' => 'big'],
        ]])->assertOk();

        // fin = (2*1 + 3*0.5) / 5 = 70; ops = 100; overall = (70*60 + 100*40) / 100 = 82
        $response->assertJsonPath('evaluation.score', 82)
            ->assertJsonPath('evaluation.band.color_token', 'success')
            ->assertJsonPath('evaluation.pillars.0.score', 70)
            ->assertJsonPath('evaluation.improvements.0.code', 'margin');
        $this->assertSame($before, $counts());

        // Invalid rule set -> same validation as publish.
        $set->bands()->where('min', 50)->delete();
        $this->actingAs($this->admin)->postJson(route('admin.readiness.preview', $set), ['answers' => []])->assertStatus(422)->assertJsonStructure(['errors' => ['bands']]);
    }

    public function test_index_exposes_pillars_bands_and_typed_criteria(): void
    {
        $this->fullDraft();
        $this->actingAs($this->admin)->get(route('admin.readiness.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Readiness/Admin')
                ->has('ruleSets.0.pillars', 2)->has('ruleSets.0.bands', 2)
                ->where('ruleSets.0.criteria.1.type', 'numeric_bands'));
    }
}
