<?php

namespace Tests\Feature\Ai;

use App\Ai\Models\AiEscalation;
use App\Ai\Models\KnowledgeArticle;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\AiDemoSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AiDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private function asLocal(): void
    {
        $this->app['env'] = 'local';
        config(['demo.password' => 'test-only-password']);
    }

    public function test_refuses_outside_local(): void
    {
        $this->expectException(RuntimeException::class);
        $this->seed(AiDemoSeeder::class);
    }

    public function test_requires_demo_users(): void
    {
        $this->asLocal();
        $this->expectExceptionMessage('DemoSeeder');
        $this->seed(AiDemoSeeder::class);
    }

    public function test_seeds_and_is_idempotent_and_visible_to_demo_accounts(): void
    {
        $this->asLocal();
        $this->seed(DemoSeeder::class);
        $this->seed(AiDemoSeeder::class);
        $this->seed(AiDemoSeeder::class);

        $titles = collect(AiDemoSeeder::ARTICLES)->pluck('title');
        $this->assertSame(
            count(AiDemoSeeder::ARTICLES),
            KnowledgeArticle::query()->whereIn('title', $titles)->count(),
        );
        $published = collect(AiDemoSeeder::ARTICLES)->where('status', 'published')->count();
        $this->assertSame($published, KnowledgeArticle::query()->currentlyApproved()->count());
        $this->assertGreaterThanOrEqual(1, KnowledgeArticle::query()->where('topic', 'financial_readiness')->currentlyApproved()->count());
        $this->assertGreaterThanOrEqual(1, KnowledgeArticle::query()->where('topic', 'business_planning')->currentlyApproved()->count());
        $this->assertSame(1, AiEscalation::query()->count());
        $sme = User::query()->where('email', 'sme@beacon.test')->sole();
        $this->assertSame(1, $sme->notifications()->count());
        $this->assertNotNull(AiEscalation::query()->sole()->enterprise_id);

        $esc = AiEscalation::query()->sole();
        foreach (['sme@beacon.test' => 200, 'advisor@beacon.test' => 200, 'demo-admin@beacon.test' => 200, 'mfi@beacon.test' => 403] as $email => $code) {
            $this->actingAs(User::query()->where('email', $email)->sole())
                ->getJson(route('ai.escalations.show', $esc->public_id))->assertStatus($code);
        }
        $this->actingAs($sme)->getJson(route('ai.knowledge.published'))->assertOk()->assertJsonCount($published);
        $this->assertSame(UserRole::SmeUser, $sme->role);
    }
}
