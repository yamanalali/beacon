<?php

namespace Tests\Feature\Ai;

use App\Ai\Models\AiEscalation;
use App\Enums\UserRole;
use App\Models\Enterprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscalationTest extends TestCase
{
    use AiTestHelpers, RefreshDatabase;

    public function test_sme_escalates_with_permitted_context_and_sees_only_own(): void
    {
        $e = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $sme = $this->userWithRole(UserRole::SmeUser);
        $e->members()->attach($sme->id);
        $otherSme = $this->userWithRole(UserRole::SmeUser);

        $id = $this->actingAs($sme)->postJson(route('ai.escalations.store'), ['question' => 'سؤال غير مغطى', 'enterprise' => $e->public_id])
            ->assertCreated()->assertJsonPath('status', 'open')->json('id');
        $numericId = AiEscalation::query()->where('public_id', $id)->value('id');
        $this->getJson('/ai/escalations/'.$numericId)->assertNotFound();
        $this->assertDatabaseHas('ai_escalations', ['id' => $numericId, 'enterprise_id' => $e->id, 'question' => 'سؤال غير مغطى']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.escalation.created', 'target_id' => $numericId]);

        $this->getJson(route('ai.escalations.show', $id))->assertOk();
        $this->getJson(route('ai.escalations.index'))->assertJsonPath('total', 1);

        $this->actingAs($otherSme)->getJson(route('ai.escalations.show', $id))->assertForbidden();
        $this->getJson(route('ai.escalations.index'))->assertJsonPath('total', 0);
    }

    public function test_advisor_sees_only_assigned_enterprise_escalations_and_admin_sees_all(): void
    {
        $e = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $sme = $this->userWithRole(UserRole::SmeUser);
        $e->members()->attach($sme->id);
        $id = $this->actingAs($sme)->postJson(route('ai.escalations.store'), ['question' => 'سؤال', 'enterprise' => $e->public_id])->json('id');

        $assigned = $this->userWithRole(UserRole::BeaconAdvisor);
        $e->advisors()->attach($assigned->id);
        $unassigned = $this->userWithRole(UserRole::BeaconAdvisor);

        $this->actingAs($assigned)->getJson(route('ai.escalations.show', $id))->assertOk();
        $this->getJson(route('ai.escalations.index'))->assertJsonPath('total', 1);
        $this->actingAs($unassigned)->getJson(route('ai.escalations.show', $id))->assertForbidden();
        $this->getJson(route('ai.escalations.index'))->assertJsonPath('total', 0);
        $this->actingAs($this->userWithRole(UserRole::BeaconAdmin))->getJson(route('ai.escalations.show', $id))->assertOk();
        $this->actingAs($this->userWithRole(UserRole::MfiUser))->getJson(route('ai.escalations.show', $id))->assertForbidden();
        $this->actingAs($this->userWithRole(UserRole::TechnicalAdmin))->getJson(route('ai.escalations.show', $id))->assertForbidden();
    }
}
