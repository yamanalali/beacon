<?php

namespace Tests\Feature\Ai;

use App\Ai\Contracts\AiProvider;
use App\Ai\Drivers\FakeAiProvider;
use App\Ai\Models\KnowledgeArticle;
use App\Enums\UserRole;
use App\Models\User;

trait AiTestHelpers
{
    protected FakeAiProvider $fakeAi;

    protected function setUpFakeAi(): void
    {
        $this->fakeAi = new FakeAiProvider;
        $this->app->instance(AiProvider::class, $this->fakeAi);
    }

    protected function userWithRole(UserRole $role, bool $active = true): User
    {
        $u = User::factory()->create(['role' => $role]);
        $u->forceFill(['is_active' => $active])->save();

        return $u;
    }

    protected function article(string $status = 'published', array $attrs = []): KnowledgeArticle
    {
        $a = new KnowledgeArticle(array_merge([
            'title' => 'تسجيل المصاريف اليومية',
            'body' => 'سجّل كل مصروف مع الإيصال في نفس اليوم.',
            'topic' => 'bookkeeping',
        ], $attrs));
        $a->status = $status;
        $a->published_at = $status === 'published' ? now() : null;
        $a->save();

        return $a;
    }
}
