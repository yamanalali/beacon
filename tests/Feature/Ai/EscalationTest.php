<?php

namespace Tests\Feature\Ai;

use App\Ai\Models\AiEscalation;
use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Notifications\EscalationOpenedNotification;
use App\Notifications\EscalationRepliedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    public function test_assigned_advisor_can_reply_and_unassigned_cannot(): void
    {
        $e = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $sme = $this->userWithRole(UserRole::SmeUser);
        $e->members()->attach($sme->id);
        $id = $this->actingAs($sme)->postJson(route('ai.escalations.store'), [
            'question' => 'كيف أصنّف الإيجار؟',
            'enterprise' => $e->public_id,
        ])->json('id');

        $assigned = $this->userWithRole(UserRole::BeaconAdvisor);
        $e->advisors()->attach($assigned->id);
        $unassigned = $this->userWithRole(UserRole::BeaconAdvisor);

        $this->actingAs($unassigned)->post(route('ai.pages.escalations.reply', $id), [
            'staff_reply' => 'لا يمكن للمستشار غير المعيّن الرد.',
        ])->assertForbidden();

        $this->actingAs($sme)->post(route('ai.pages.escalations.reply', $id), [
            'staff_reply' => 'لا يرد صاحب السؤال على نفسه.',
        ])->assertForbidden();

        $this->actingAs($assigned)->get(route('ai.pages.escalations.show', $id))
            ->assertInertia(fn ($page) => $page
                ->component('Ai/Escalations/Show')
                ->where('escalation.can_reply', true)
                ->where('escalation.status', 'open'));

        $this->post(route('ai.pages.escalations.reply', $id), [
            'staff_reply' => 'صنّف الإيجار كمصروف تشغيلي مع إيصال الإيجار.',
        ])->assertRedirect(route('ai.pages.escalations.show', $id));

        $this->assertDatabaseHas('ai_escalations', [
            'public_id' => $id,
            'status' => AiEscalation::STATUS_ANSWERED,
            'replied_by' => $assigned->id,
            'staff_reply' => 'صنّف الإيجار كمصروف تشغيلي مع إيصال الإيجار.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ai.escalation.replied',
            'actor_id' => $assigned->id,
        ]);

        $this->get(route('ai.pages.escalations.show', $id))
            ->assertInertia(fn ($page) => $page
                ->where('escalation.can_reply', false)
                ->where('escalation.status', 'answered')
                ->where('escalation.staff_reply', 'صنّف الإيجار كمصروف تشغيلي مع إيصال الإيجار.'));

        $this->post(route('ai.pages.escalations.reply', $id), [
            'staff_reply' => 'محاولة رد ثانٍ',
        ])->assertForbidden();

        $reply = $sme->notifications()->where('type', EscalationRepliedNotification::class)->first();
        $this->assertNotNull($reply);
        $this->assertSame('escalation_replied', $reply->data['type']);
        $this->assertSame($id, $reply->data['escalation_public_id']);
        $this->assertSame(0, $assigned->notifications()->where('type', EscalationRepliedNotification::class)->count());
    }

    public function test_creating_escalation_notifies_assigned_advisors_only(): void
    {
        Notification::fake();

        $e = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $sme = $this->userWithRole(UserRole::SmeUser);
        $e->members()->attach($sme->id);
        $assigned = $this->userWithRole(UserRole::BeaconAdvisor);
        $e->advisors()->attach($assigned->id);
        $unassigned = $this->userWithRole(UserRole::BeaconAdvisor);

        $id = $this->actingAs($sme)->postJson(route('ai.escalations.store'), [
            'question' => 'سؤال يحتاج مستشاراً',
            'enterprise' => $e->public_id,
        ])->assertCreated()->json('id');

        Notification::assertSentTo(
            $assigned,
            EscalationOpenedNotification::class,
            function (EscalationOpenedNotification $n, array $channels) use ($id, $e) {
                return $n->escalationPublicId === $id
                    && $n->enterprisePublicId === $e->public_id
                    && $channels === ['database'];
            },
        );
        Notification::assertNotSentTo($unassigned, EscalationOpenedNotification::class);
        Notification::assertNotSentTo($sme, EscalationOpenedNotification::class);
    }
}
