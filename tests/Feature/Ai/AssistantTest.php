<?php

namespace Tests\Feature\Ai;

use App\Ai\Services\KnowledgeAssistantService;
use App\Enums\UserRole;
use App\Models\Enterprise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use AiTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFakeAi();
    }

    private function enterprise(): Enterprise
    {
        return Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
    }

    public static function allowedRoles(): array
    {
        return [[UserRole::SmeUser], [UserRole::BeaconAdvisor], [UserRole::BeaconAdmin]];
    }

    /** @dataProvider allowedRoles */
    public function test_supported_question_returns_labelled_answer_with_source(UserRole $role): void
    {
        $a = $this->article();
        $this->actingAs($this->userWithRole($role))
            ->postJson(route('ai.assistant.ask'), ['question' => 'كيف أسجل المصاريف؟'])
            ->assertOk()
            ->assertJsonPath('supported', true)
            ->assertJsonPath('ai_generated', true)
            ->assertJsonPath('label', KnowledgeAssistantService::AI_LABEL)
            ->assertJsonPath('sources.0.id', $a->id);
    }

    public function test_draft_or_archived_knowledge_is_never_used(): void
    {
        $this->article('draft');
        $this->article('archived');
        $this->actingAs($this->userWithRole(UserRole::SmeUser))
            ->postJson(route('ai.assistant.ask'), ['question' => 'كيف أسجل المصاريف؟'])
            ->assertOk()->assertJsonPath('supported', false)->assertJsonPath('can_escalate', true);
        $this->assertSame([], $this->fakeAi->calls, 'Provider must not be called without approved knowledge');
    }

    public function test_provider_failure_degrades_gracefully(): void
    {
        $this->article();
        $this->fakeAi->failing = true;
        $this->actingAs($this->userWithRole(UserRole::SmeUser))
            ->postJson(route('ai.assistant.ask'), ['question' => 'كيف أسجل المصاريف؟'])
            ->assertOk()->assertJsonPath('supported', false)->assertJsonPath('can_escalate', true)
            ->assertJsonMissing(['exception']);
    }

    public function test_only_knowledge_is_sent_to_provider(): void
    {
        $this->article();
        $sme = $this->userWithRole(UserRole::SmeUser);
        $e = $this->enterprise();
        $e->members()->attach($sme->id);
        $this->actingAs($sme)->postJson(route('ai.assistant.ask'), ['question' => 'المصاريف', 'enterprise' => $e->public_id])->assertOk();
        $sent = json_encode($this->fakeAi->calls, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString($sme->email, $sent);
        $this->assertStringNotContainsString('منشأة', $sent);
    }

    /** @dataProvider deniedRoles */
    public function test_mfi_and_technical_admin_cannot_use_assistant(UserRole $role): void
    {
        $this->actingAs($this->userWithRole($role))
            ->postJson(route('ai.assistant.ask'), ['question' => 'سؤال عام'])->assertForbidden();
        $this->postJson(route('ai.escalations.store'), ['question' => 'سؤال عام'])->assertForbidden();
        $this->getJson(route('ai.escalations.index'))->assertForbidden();
    }

    public static function deniedRoles(): array
    {
        return [[UserRole::MfiUser], [UserRole::TechnicalAdmin]];
    }

    public function test_sme_cannot_use_another_enterprise_as_context(): void
    {
        $sme = $this->userWithRole(UserRole::SmeUser);
        $other = $this->enterprise();
        $this->actingAs($sme);
        $this->postJson(route('ai.assistant.ask'), ['question' => 'سؤال', 'enterprise' => $other->public_id])->assertForbidden();
        $this->postJson(route('ai.escalations.store'), ['question' => 'سؤال', 'enterprise' => $other->public_id])->assertForbidden();
        $this->assertDatabaseCount('ai_escalations', 0);
    }

    public function test_unassigned_advisor_cannot_use_enterprise_context(): void
    {
        $this->actingAs($this->userWithRole(UserRole::BeaconAdvisor))
            ->postJson(route('ai.assistant.ask'), ['question' => 'سؤال', 'enterprise' => $this->enterprise()->public_id])
            ->assertForbidden();
    }

    public function test_assistant_is_rate_limited(): void
    {
        config(['ai.assistant_rate_per_minute' => 2]);
        $this->actingAs($this->userWithRole(UserRole::SmeUser));
        $this->postJson(route('ai.assistant.ask'), ['question' => 'سؤال أول'])->assertOk();
        $this->postJson(route('ai.assistant.ask'), ['question' => 'سؤال ثاني'])->assertOk();
        $this->postJson(route('ai.assistant.ask'), ['question' => 'سؤال ثالث'])->assertStatus(429);
    }

    public function test_question_is_validated(): void
    {
        $this->actingAs($this->userWithRole(UserRole::SmeUser))
            ->postJson(route('ai.assistant.ask'), ['question' => ''])->assertUnprocessable();
    }
}
