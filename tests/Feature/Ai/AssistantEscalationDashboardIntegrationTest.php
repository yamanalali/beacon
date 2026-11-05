<?php

namespace Tests\Feature\Ai;

use App\Ai\Models\AiEscalation;
use App\Enums\UserRole;
use App\Models\Enterprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantEscalationDashboardIntegrationTest extends TestCase
{
    use AiTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFakeAi();
        $this->withoutVite();
    }

    public function test_unsupported_question_escalates_and_appears_on_beacon_dashboard(): void
    {
        $enterprise = Enterprise::query()->create([
            'business_name' => 'منشأة الداشبورد',
            'location' => 'damascus',
        ]);
        $sme = $this->userWithRole(UserRole::SmeUser);
        $enterprise->members()->attach($sme->id);
        $admin = $this->userWithRole(UserRole::BeaconAdmin);

        $this->actingAs($admin)->get(route('dashboard.beacon'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('programme.kpis.open_escalations_count', 0));

        $ask = $this->actingAs($sme)->postJson(route('ai.assistant.ask'), [
            'question' => 'سؤال غير موجود في المعرفة المعتمدة',
            'enterprise' => $enterprise->public_id,
        ])->assertOk();

        $this->assertFalse((bool) $ask->json('supported'));
        $this->assertTrue((bool) $ask->json('can_escalate'));

        $escalationId = $this->actingAs($sme)->postJson(route('ai.escalations.store'), [
            'question' => 'سؤال غير موجود في المعرفة المعتمدة',
            'enterprise' => $enterprise->public_id,
        ])->assertCreated()->json('id');

        $this->assertDatabaseHas('ai_escalations', [
            'public_id' => $escalationId,
            'status' => 'open',
            'enterprise_id' => $enterprise->id,
        ]);

        $this->actingAs($admin)->get(route('dashboard.beacon'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('programme.kpis.open_escalations_count', 1));

        $this->actingAs($sme)->get(route('dashboard.sme'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('kpis.open_escalations_count', 1));
    }

    public function test_non_open_escalation_no_longer_counts_on_dashboard(): void
    {
        $enterprise = Enterprise::query()->create([
            'business_name' => 'منشأة',
            'location' => 'damascus',
        ]);
        $sme = $this->userWithRole(UserRole::SmeUser);
        $enterprise->members()->attach($sme->id);
        $admin = $this->userWithRole(UserRole::BeaconAdmin);

        $publicId = $this->actingAs($sme)->postJson(route('ai.escalations.store'), [
            'question' => 'يحتاج رد',
            'enterprise' => $enterprise->public_id,
        ])->json('id');

        // No reply workflow is implemented yet; closed statuses still must drop from the open KPI.
        AiEscalation::query()->where('public_id', $publicId)->update(['status' => 'resolved']);

        $this->actingAs($admin)->get(route('dashboard.beacon'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('programme.kpis.open_escalations_count', 0));
    }
}
