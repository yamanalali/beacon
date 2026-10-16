<?php

namespace Tests\Feature\Ai;

use App\Ai\Models\AiEscalation;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\ComplianceDeadlineReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Page;
use Tests\TestCase;

class AiPagesTest extends TestCase
{
    use AiTestHelpers, RefreshDatabase;

    private const PAGINATION = ['data', 'links', 'meta'];

    public static function roles(): array
    {
        return [
            'sme' => [UserRole::SmeUser, true, false],
            'advisor' => [UserRole::BeaconAdvisor, true, false],
            'admin' => [UserRole::BeaconAdmin, true, true],
            'mfi' => [UserRole::MfiUser, false, false],
            'tech' => [UserRole::TechnicalAdmin, false, false],
        ];
    }

    private function escalationFor(User $u): AiEscalation
    {
        return AiEscalation::query()->create(['user_id' => $u->id, 'question' => 'سؤال تجريبي', 'context' => [], 'status' => 'open']);
    }

    private function assertNoNumericIds(array $props): void
    {
        array_walk_recursive($props, function ($v, $k) {
            if (in_array($k, ['id', 'user_id', 'enterprise_id', 'created_by', 'updated_by'], true)) {
                $this->assertFalse(is_int($v) || (is_string($v) && ctype_digit($v)), "numeric $k leaked");
            }
        });
    }

    /** @dataProvider roles */
    public function test_role_access(UserRole $role, bool $aiRole, bool $admin): void
    {
        $u = $this->userWithRole($role);
        $this->actingAs($u);
        $codes = [route('ai.pages.assistant'), route('ai.pages.escalations.index'), route('ai.pages.knowledge.published')];
        foreach ($codes as $url) {
            $this->get($url)->assertStatus($aiRole ? 200 : 403);
        }
        $this->get(route('ai.pages.knowledge.index'))->assertStatus($admin ? 200 : 403);
        $this->get(route('notifications.page'))->assertOk();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('ai.pages.assistant'))->assertRedirect();
        $this->get(route('notifications.page'))->assertRedirect();
    }

    public function test_assistant_props_and_ai_availability(): void
    {
        $u = $this->userWithRole(UserRole::SmeUser);
        $this->actingAs($u)->get(route('ai.pages.assistant'))->assertInertia(fn (Page $p) => $p
            ->component('Ai/Assistant', false)->where('ai_available', false)->where('can_escalate', true)->has('question_max_length'));

        config(['ai.driver' => 'fake']);
        $this->approveAiSharing();
        $this->get(route('ai.pages.assistant'))->assertInertia(fn (Page $p) => $p->component('Ai/Assistant', false)->where('ai_available', true));
    }

    public function test_escalations_scoped_paginated_and_public_ids_only(): void
    {
        $sme = $this->userWithRole(UserRole::SmeUser);
        $other = $this->userWithRole(UserRole::SmeUser);
        $mine = $this->escalationFor($sme);
        $theirs = $this->escalationFor($other);

        $res = $this->actingAs($sme)->get(route('ai.pages.escalations.index'));
        $res->assertInertia(fn (Page $p) => $p->component('Ai/Escalations/Index', false)
            ->has('escalations', fn (Page $e) => $e->has('data', 1)->has('links')->has('meta')
                ->where('data.0.id', $mine->public_id)->has('data.0', fn (Page $r) => $r->hasAll(['id', 'question', 'status', 'created_at'])))
            ->where('can_escalate', true)->has('filters'));
        $this->assertNoNumericIds($res->viewData('page')['props']);

        $this->get(route('ai.pages.escalations.show', $mine->public_id))->assertInertia(fn (Page $p) => $p
            ->component('Ai/Escalations/Show', false)->where('escalation.id', $mine->public_id)->has('escalation.enterprise_id'));
        $this->get(route('ai.pages.escalations.show', $theirs->public_id))->assertForbidden();
        $this->get('/ai/pages/escalations/'.$mine->id)->assertNotFound();

        $this->actingAs($this->userWithRole(UserRole::BeaconAdmin))->get(route('ai.pages.escalations.index'))
            ->assertInertia(fn (Page $p) => $p->has('escalations.data', 2));
        $this->get(route('ai.pages.escalations.show', $theirs->public_id))->assertOk();
    }

    public function test_knowledge_pages_props(): void
    {
        $pub = $this->article();
        $this->article('draft', ['title' => 'مسودة']);

        $res = $this->actingAs($this->userWithRole(UserRole::SmeUser))->get(route('ai.pages.knowledge.published'));
        $res->assertInertia(fn (Page $p) => $p->component('Ai/Knowledge/Published', false)
            ->has('articles.data', 1)->where('articles.data.0.id', $pub->public_id)->missing('articles.data.0.status')
            ->has('articles.links')->has('articles.meta.total')->has('topics')->has('filters')
            ->where('can_manage', false)->where('ai_available', false));
        $this->assertNoNumericIds($res->viewData('page')['props']);

        $res = $this->actingAs($this->userWithRole(UserRole::BeaconAdmin))->get(route('ai.pages.knowledge.index', ['status' => 'draft']));
        $res->assertInertia(fn (Page $p) => $p->component('Ai/Knowledge/Index', false)
            ->has('articles.data', 1)->where('articles.data.0.status', 'draft')->has('statuses')->has('topics')
            ->where('can_manage', true)->where('filters.status', 'draft'));
        $this->assertNoNumericIds($res->viewData('page')['props']);
    }

    public function test_notifications_page_shows_only_own(): void
    {
        $me = $this->userWithRole(UserRole::SmeUser);
        $other = $this->userWithRole(UserRole::SmeUser);
        $n = new ComplianceDeadlineReminder('ent-public', 'منشأة', 'متطلب', '2026-10-20');
        $me->notify($n);
        $other->notify($n);

        $res = $this->actingAs($me)->get(route('notifications.page'));
        $res->assertInertia(fn (Page $p) => $p->component('Notifications/Index', false)
            ->has('notifications.data', 1)->has('notifications.links')->has('notifications.meta')
            ->where('unread_count', 1)->has('notifications.data.0', fn (Page $r) => $r->hasAll(['id', 'data', 'read_at', 'created_at'])));
        $this->assertNoNumericIds($res->viewData('page')['props']);
    }
}
