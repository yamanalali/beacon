<?php

namespace Tests\Feature\Readiness;

use App\Dashboard\SmeDashboardData;
use App\Models\ReadinessAssessment;
use App\Models\ReadinessAssessmentSnapshot;
use App\Models\Referral;
use App\Support\MfiMatcher;
use App\Support\ReferralPackageBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReadCurrentSnapshotTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpReadiness();
    }

    /** Finalized at 50 (a met, b unmet, equal weights). */
    private function finalized(): array
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

    /** @return array{match:array<string,mixed>,dashboard:?string,referral:array<string,mixed>} */
    private function readScores($enterprise, ReadinessAssessment $assessment): array
    {
        $institution = $this->institution();
        $institution->eligibilityCriteria()->create(['label_ar' => 'جاهزية', 'type' => 'min_readiness_score', 'parameters' => ['min_score' => 40]]);
        $match = app(MfiMatcher::class)->match($enterprise, $institution)[0];

        $sme = $this->smeOf($enterprise);
        $dashboard = app(SmeDashboardData::class)->build($sme)['kpis']['readiness_score'];

        $referral = new Referral(['enterprise_id' => $enterprise->id, 'readiness_assessment_id' => $assessment->id, 'disclosed_items' => ['readiness_assessment']]);
        $package = app(ReferralPackageBuilder::class)->build($referral);

        return ['match' => $match, 'dashboard' => $dashboard, 'referral' => $package['readiness_assessment']];
    }

    public function test_weight_change_does_not_affect_matcher_dashboard_or_referral(): void
    {
        [$enterprise, , $assessment] = $this->finalized();
        DB::table('readiness_criteria')->where('code', 'b')->update(['weight' => 3]);

        $r = $this->readScores($enterprise, $assessment);
        $this->assertSame(MfiMatcher::MET, $r['match']['result']);
        $this->assertStringContainsString('50', $r['match']['reason']);
        $this->assertEquals(50.0, (float) $r['dashboard']);
        $this->assertEquals(50.0, (float) $r['referral']['score']);
        $this->assertSame(1, ReadinessAssessmentSnapshot::query()->count());
    }

    public function test_after_recalculation_all_readers_use_new_snapshot(): void
    {
        [$enterprise, $advisor, $assessment] = $this->finalized();
        DB::table('readiness_criteria')->where('code', 'b')->update(['weight' => 3]);
        $this->actingAs($advisor)->post(route('readiness-assessments.recalculate', $assessment), ['reason' => 'أوزان'])->assertRedirect();

        $this->assertEquals(50.0, (float) $assessment->fresh()->score); // row stays frozen
        $r = $this->readScores($enterprise, $assessment);
        $this->assertSame(MfiMatcher::NOT_MET, $r['match']['result']);
        $this->assertStringContainsString('25', $r['match']['reason']);
        $this->assertEquals(25.0, (float) $r['dashboard']);
        $this->assertEquals(25.0, (float) $r['referral']['score']);
    }

    public function test_legacy_finalized_without_snapshot_falls_back_to_row_score(): void
    {
        [$enterprise, , $assessment] = $this->finalized();
        ReadinessAssessmentSnapshot::query()->delete();

        $r = $this->readScores($enterprise, $assessment);
        $this->assertEquals(50.0, (float) $r['dashboard']);
        $this->assertEquals(50.0, (float) $r['referral']['score']);
        $this->assertNull($r['referral']['band']);
    }
}
