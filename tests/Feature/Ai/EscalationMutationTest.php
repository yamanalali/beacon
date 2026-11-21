<?php

namespace Tests\Feature\Ai;

use App\Ai\Models\AiEscalation;
use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscalationMutationTest extends TestCase
{
    use AiTestHelpers, RefreshDatabase;

    /** @return array{0: AiEscalation, 1: User, 2: User, 3: Enterprise} escalation, sme, assigned advisor, enterprise */
    private function answeredEscalation(): array
    {
        $enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $sme = $this->userWithRole(UserRole::SmeUser);
        $enterprise->members()->attach($sme->id);
        $advisor = $this->userWithRole(UserRole::BeaconAdvisor);
        $enterprise->advisors()->attach($advisor->id);

        $id = $this->actingAs($sme)->postJson(route('ai.escalations.store'), [
            'question' => 'كيف أصنّف الإيجار؟',
            'enterprise' => $enterprise->public_id,
        ])->assertCreated()->json('id');

        $this->actingAs($advisor)->post(route('ai.pages.escalations.reply', $id), [
            'staff_reply' => 'مصروف تشغيلي.',
        ])->assertRedirect();

        return [AiEscalation::query()->where('public_id', $id)->firstOrFail(), $sme, $advisor, $enterprise];
    }

    public function test_assigned_advisor_can_reopen_answered_escalation_and_reply_is_cleared_but_audited(): void
    {
        [$escalation, , $advisor] = $this->answeredEscalation();

        $this->actingAs($advisor)
            ->post(route('ai.pages.escalations.reopen', $escalation))
            ->assertRedirect(route('ai.pages.escalations.show', $escalation))
            ->assertSessionHas('success', ui('flash.escalation_reopened'));

        $fresh = $escalation->fresh();
        $this->assertSame('open', $fresh->status);
        $this->assertNull($fresh->staff_reply);
        $this->assertNull($fresh->replied_by);
        $this->assertNull($fresh->replied_at);

        $log = \App\Models\AuditLog::query()->where('action', 'ai.escalation.reopened')->firstOrFail();
        $this->assertSame($advisor->id, (int) $log->actor_id);
        $this->assertSame('مصروف تشغيلي.', $log->context['previous_staff_reply']);
    }

    public function test_admin_can_reopen_and_reply_is_possible_again(): void
    {
        [$escalation, , $advisor] = $this->answeredEscalation();
        $admin = $this->userWithRole(UserRole::BeaconAdmin);

        $this->actingAs($admin)->post(route('ai.pages.escalations.reopen', $escalation))->assertRedirect();

        $this->actingAs($advisor)->post(route('ai.pages.escalations.reply', $escalation), [
            'staff_reply' => 'رد جديد بعد إعادة الفتح.',
        ])->assertRedirect();

        $this->assertSame('answered', $escalation->fresh()->status);
    }

    public function test_open_escalation_cannot_be_reopened(): void
    {
        [$escalation, , $advisor] = $this->answeredEscalation();
        $this->actingAs($advisor)->post(route('ai.pages.escalations.reopen', $escalation))->assertRedirect();

        $this->actingAs($advisor)->post(route('ai.pages.escalations.reopen', $escalation))->assertForbidden();
    }

    public function test_unassigned_advisor_sme_mfi_and_technical_admin_cannot_reopen(): void
    {
        [$escalation, $sme] = $this->answeredEscalation();

        foreach ([
            $this->userWithRole(UserRole::BeaconAdvisor),
            $sme,
            $this->userWithRole(UserRole::MfiUser),
            $this->userWithRole(UserRole::TechnicalAdmin),
        ] as $actor) {
            $this->actingAs($actor)->post(route('ai.pages.escalations.reopen', $escalation))->assertForbidden();
        }

        $this->assertSame('answered', $escalation->fresh()->status);
    }

    public function test_asker_can_delete_own_open_escalation_and_it_is_audited(): void
    {
        $sme = $this->userWithRole(UserRole::SmeUser);
        $id = $this->actingAs($sme)->postJson(route('ai.escalations.store'), ['question' => 'سؤال للحذف'])
            ->assertCreated()->json('id');

        $this->actingAs($sme)
            ->delete(route('ai.pages.escalations.destroy', $id))
            ->assertRedirect(route('ai.pages.escalations.index'))
            ->assertSessionHas('success', ui('flash.escalation_deleted'));

        $this->assertDatabaseMissing('ai_escalations', ['public_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.escalation.deleted', 'actor_id' => $sme->id]);
    }

    public function test_asker_cannot_delete_answered_escalation_but_admin_can(): void
    {
        [$escalation, $sme] = $this->answeredEscalation();

        $this->actingAs($sme)->delete(route('ai.pages.escalations.destroy', $escalation))->assertForbidden();
        $this->assertDatabaseHas('ai_escalations', ['id' => $escalation->id]);

        $admin = $this->userWithRole(UserRole::BeaconAdmin);
        $this->actingAs($admin)->delete(route('ai.pages.escalations.destroy', $escalation))->assertRedirect();
        $this->assertDatabaseMissing('ai_escalations', ['id' => $escalation->id]);
    }

    public function test_other_users_cannot_delete_someone_elses_escalation(): void
    {
        [$escalation, , $assignedAdvisor] = $this->answeredEscalation();
        $this->actingAs($assignedAdvisor)->post(route('ai.pages.escalations.reopen', $escalation));

        foreach ([
            $this->userWithRole(UserRole::SmeUser),
            $assignedAdvisor,
            $this->userWithRole(UserRole::BeaconAdvisor),
            $this->userWithRole(UserRole::MfiUser),
            $this->userWithRole(UserRole::TechnicalAdmin),
        ] as $actor) {
            $this->actingAs($actor)->delete(route('ai.pages.escalations.destroy', $escalation))->assertForbidden();
        }

        $this->assertDatabaseHas('ai_escalations', ['id' => $escalation->id]);
    }

    public function test_show_page_exposes_reopen_and_delete_flags(): void
    {
        [$escalation, $sme, $advisor] = $this->answeredEscalation();

        $this->actingAs($advisor)->get(route('ai.pages.escalations.show', $escalation))
            ->assertInertia(fn ($page) => $page->where('escalation.can_reopen', true)->where('escalation.can_delete', false));

        $this->actingAs($sme)->get(route('ai.pages.escalations.show', $escalation))
            ->assertInertia(fn ($page) => $page->where('escalation.can_reopen', false)->where('escalation.can_delete', false));
    }
}
