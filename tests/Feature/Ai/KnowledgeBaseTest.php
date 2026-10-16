<?php

namespace Tests\Feature\Ai;

use App\Ai\Models\KnowledgeArticle;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeBaseTest extends TestCase
{
    use AiTestHelpers, RefreshDatabase;

    private function payload(): array
    {
        return ['title' => 'دليل الامتثال', 'body' => 'نص إرشادي معتمد', 'topic' => 'compliance'];
    }

    public function test_admin_can_create_publish_edit_archive(): void
    {
        $admin = $this->userWithRole(UserRole::BeaconAdmin);

        $id = $this->actingAs($admin)->postJson(route('ai.knowledge.store'), $this->payload())
            ->assertCreated()->assertJsonPath('status', 'draft')->assertJsonMissingPath('id')->json('public_id');
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $id);
        $numericId = KnowledgeArticle::query()->where('public_id', $id)->value('id');
        $this->postJson('/ai/admin/knowledge/'.$numericId.'/publish')->assertNotFound();

        $this->postJson(route('ai.knowledge.publish', $id))->assertOk()->assertJsonPath('status', 'published');

        $this->putJson(route('ai.knowledge.update', $id), ['body' => 'نص معدّل'] + $this->payload())
            ->assertOk()->assertJsonPath('version', 2)->assertJsonPath('status', 'draft');

        $this->postJson(route('ai.knowledge.archive', $id))->assertOk()->assertJsonPath('status', 'archived');
        $this->getJson(route('ai.knowledge.index'))->assertOk()->assertJsonPath('total', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.knowledge.archived', 'target_id' => $numericId]);
    }

    public function test_validation_rejects_unknown_topic(): void
    {
        $this->actingAs($this->userWithRole(UserRole::BeaconAdmin))
            ->postJson(route('ai.knowledge.store'), ['topic' => 'x'] + $this->payload())
            ->assertUnprocessable();
    }

    public static function nonAdminRoles(): array
    {
        return [[UserRole::SmeUser], [UserRole::BeaconAdvisor], [UserRole::MfiUser], [UserRole::TechnicalAdmin]];
    }

    /** @dataProvider nonAdminRoles */
    public function test_non_admin_cannot_manage_knowledge(UserRole $role): void
    {
        $a = $this->article('draft');
        $this->actingAs($this->userWithRole($role));
        $this->getJson(route('ai.knowledge.index'))->assertForbidden();
        $this->postJson(route('ai.knowledge.store'), $this->payload())->assertForbidden();
        $this->putJson(route('ai.knowledge.update', $a), $this->payload())->assertForbidden();
        $this->postJson(route('ai.knowledge.publish', $a))->assertForbidden();
        $this->postJson(route('ai.knowledge.archive', $a))->assertForbidden();
        $this->assertDatabaseCount('knowledge_articles', 1);
    }

    public function test_inactive_admin_is_blocked(): void
    {
        $this->actingAs($this->userWithRole(UserRole::BeaconAdmin, false))
            ->getJson(route('ai.knowledge.index'))->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('ai.knowledge.index'))->assertRedirect('/login');
        $this->postJson(route('ai.assistant.ask'), ['question' => 'سؤال'])->assertUnauthorized();
    }

    public static function readerRoles(): array
    {
        return [[UserRole::SmeUser, 200], [UserRole::BeaconAdvisor, 200], [UserRole::BeaconAdmin, 200], [UserRole::MfiUser, 403], [UserRole::TechnicalAdmin, 403]];
    }

    /** @dataProvider readerRoles */
    public function test_published_list_shows_only_current_approved(UserRole $role, int $status): void
    {
        $this->article('published', ['title' => 'منشور']);
        $this->article('draft', ['title' => 'مسودة']);
        $this->article('archived', ['title' => 'مؤرشف']);
        $this->article('published', ['title' => 'منتهي', 'effective_until' => now()->subDay()]);

        $res = $this->actingAs($this->userWithRole($role))->getJson(route('ai.knowledge.published'))->assertStatus($status);
        if ($status === 200) {
            $res->assertJsonCount(1)->assertJsonPath('0.title', 'منشور');
        }
    }
}
