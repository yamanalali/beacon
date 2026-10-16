<?php

namespace Tests\Feature\Readiness;

use App\Models\ReadinessAssessment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResultScopedBindingTest extends TestCase
{
    use RefreshDatabase, ReadinessTestHelpers;

    public function test_result_from_another_assessment_returns_404(): void
    {
        $this->setUpReadiness();
        $this->publishedRuleSet([['code' => 'a', 'weight' => 1]]);
        $first = $this->enterprise('أولى');
        $second = $this->enterprise('ثانية');
        $advisor = $this->advisorOf($first);
        $second->advisors()->attach($advisor->id);

        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $first))->assertRedirect();
        $this->actingAs($advisor)->post(route('enterprises.readiness.store', $second))->assertRedirect();
        [$a, $b] = ReadinessAssessment::query()->orderBy('id')->get()->all();
        $foreign = $b->results()->firstOrFail();

        $this->actingAs($advisor)->put(route('readiness-assessments.results.update', [$a, $foreign]), ['outcome' => 'met'])->assertNotFound();
        $this->assertNotSame('met', $foreign->fresh()->outcome);

        $own = $a->results()->firstOrFail();
        $this->actingAs($advisor)->put(route('readiness-assessments.results.update', [$a, $own]), ['outcome' => 'met'])->assertRedirect();
        $this->assertSame('met', $own->fresh()->outcome);
    }
}
