<?php

namespace Tests\Feature;

use App\Ai\Models\KnowledgeArticle;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleAndLandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_home_renders_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Landing/Home')
                ->where('locale', 'ar')
                ->where('dir', 'rtl')
                ->has('translations'));
    }

    public function test_guest_can_switch_locale_to_english(): void
    {
        $this->from('/')
            ->post('/locale', ['locale' => 'en'])
            ->assertRedirect('/');

        $this->assertSame('en', session('locale'));

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Landing/Home')
                ->where('locale', 'en')
                ->where('dir', 'ltr')
                ->has('translations'));

        $this->assertSame(
            'Clearer records for SMEs',
            \App\Support\UiTranslations::get('landing.hero.title', [], 'en'),
        );
    }

    public function test_guest_marketing_pages_render(): void
    {
        foreach ([
            '/about' => 'Landing/About',
            '/case-studies' => 'Landing/CaseStudies',
            '/knowledge' => 'Landing/Knowledge',
            '/contact' => 'Landing/Contact',
        ] as $path => $component) {
            $this->get($path)
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where('locale', 'ar')
                    ->where('dir', 'rtl'));
        }
    }

    public function test_guest_case_study_detail_renders_and_rejects_unknown_slug(): void
    {
        $this->get('/case-studies/records')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Landing/CaseStudyShow')
                ->where('slug', 'records'));

        $this->get('/case-studies/not-a-case')->assertNotFound();
    }

    public function test_guest_knowledge_lists_only_currently_approved_articles(): void
    {
        $published = $this->makeArticle('published', ['title' => 'مقال منشور']);
        $this->makeArticle('draft', ['title' => 'مسودة']);
        $this->makeArticle('archived', ['title' => 'مؤرشف']);
        $this->makeArticle('published', [
            'title' => 'منتهي',
            'effective_until' => now()->subDay()->toDateString(),
        ]);

        $this->get('/knowledge')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Landing/Knowledge')
                ->has('articles.data', 1)
                ->where('articles.data.0.title', 'مقال منشور')
                ->where('articles.data.0.id', $published->public_id));
    }

    public function test_guest_knowledge_detail_allows_approved_and_hides_others(): void
    {
        $published = $this->makeArticle('published', ['title' => 'مقال تفصيلي', 'body' => 'نص معتمد']);
        $draft = $this->makeArticle('draft', ['title' => 'مسودة خاصة']);

        $this->get('/knowledge/'.$published->public_id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Landing/KnowledgeShow')
                ->where('article.title', 'مقال تفصيلي')
                ->where('article.body', 'نص معتمد'));

        $this->get('/knowledge/'.$draft->public_id)->assertNotFound();
        $this->get('/knowledge/01INVALIDPUBLICID00000000')->assertNotFound();
    }

    private function makeArticle(string $status, array $attrs = []): KnowledgeArticle
    {
        $article = new KnowledgeArticle(array_merge([
            'title' => 'عنوان',
            'body' => 'محتوى',
            'topic' => 'bookkeeping',
        ], $attrs));
        $article->status = $status;
        $article->published_at = $status === 'published' ? now() : null;
        $article->save();

        return $article;
    }

    public function test_authenticated_sme_home_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SmeUser,
            'signature_skipped_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('dashboard.sme'));
    }

    public function test_authenticated_sme_without_signature_is_sent_to_onboarding(): void
    {
        $user = User::factory()->needsOnboarding()->create(['role' => UserRole::SmeUser]);

        $this->actingAs($user)
            ->get('/dashboard/sme')
            ->assertRedirect(route('onboarding.show'));
    }

    public function test_authenticated_user_locale_persists_on_user_record(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::BeaconAdmin,
            'locale' => null,
        ]);

        $this->actingAs($user)
            ->from('/account')
            ->post('/locale', ['locale' => 'en'])
            ->assertRedirect('/account');

        $this->assertSame('en', $user->fresh()->locale);
        $this->assertSame('en', session('locale'));
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $this->from('/')
            ->post('/locale', ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');
    }
}
