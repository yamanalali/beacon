<?php

namespace Tests\Feature\Ai;

use App\Ai\Finance\AiReceiptExtractor;
use App\Ai\Finance\AiTransactionCategorizer;
use App\Contracts\ReceiptExtractor;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinanceAiTest extends TestCase
{
    use AiTestHelpers, RefreshDatabase;

    private Enterprise $enterprise;

    private User $sme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFakeAi();
        Storage::fake('local');
        $this->enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $this->sme = $this->userWithRole(UserRole::SmeUser);
        $this->enterprise->members()->attach($this->sme->id);
    }

    private function record(array $data): void
    {
        $this->actingAs($this->sme)->post(route('enterprises.transactions.store', $this->enterprise), array_merge([
            'transaction_date' => '2026-09-10', 'currency' => 'SYP', 'payment_status' => 'paid',
            'type' => 'expense', 'category' => 'rent', 'amount' => '100',
        ], $data))->assertSessionHasNoErrors();
    }

    private function query(): array
    {
        return ['enterprise' => $this->enterprise, 'from' => '2026-09-01', 'to' => '2026-09-30'];
    }

    public function test_extractor_returns_review_only_suggestions_and_never_creates_transactions(): void
    {
        $this->actingAs($this->sme)->post(route('enterprises.documents.store', $this->enterprise), ['file' => UploadedFile::fake()->image('r.jpg')]);
        $r = app(AiReceiptExtractor::class)->extract(Document::query()->sole());
        $this->assertSame('125.50', $r->amount);
        $this->assertTrue($r->needsReview);
        $this->assertFalse($r->failed);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_extractor_failure_falls_back(): void
    {
        $this->actingAs($this->sme)->post(route('enterprises.documents.store', $this->enterprise), ['file' => UploadedFile::fake()->image('r.jpg')]);
        $this->fakeAi->failing = true;
        $this->assertTrue(app(AiReceiptExtractor::class)->extract(Document::query()->sole())->failed);
    }

    public function test_categorizer_only_suggests_allowed_categories_and_sends_minimum_data(): void
    {
        $c = app(AiTransactionCategorizer::class);
        $allowed = config('bookkeeping.categories.expense');
        $s = $c->suggest('expense', 'دفعة إيجار المحل', null, $allowed);
        $this->assertSame('rent', $s->category);
        $this->assertTrue($s->needsReview);
        $this->assertNull($c->suggest('expense', 'شيء غير معروف', null, $allowed));
        $this->assertSame(['type', 'description', 'counterparty', 'allowedCategories'], array_keys($this->fakeAi->calls[0]['suggestCategory']));
        $this->fakeAi->failing = true;
        $this->assertNull($c->suggest('expense', 'إيجار', null, $allowed));
    }

    public function test_ai_bindings_only_when_driver_enabled(): void
    {
        $this->assertSame('manual', app(ReceiptExtractor::class)->name());
    }

    public function test_review_flags_duplicates_and_missing_info_without_modifying(): void
    {
        $this->record(['counterparty' => 'مالك المحل']);
        $this->record(['counterparty' => 'مالك المحل']);
        $this->record(['amount' => '50', 'category' => 'transport']);
        $before = FinancialTransaction::query()->get()->toArray();

        $flags = $this->actingAs($this->sme)->getJson(route('ai.finance.flags', $this->query()))->assertOk()->json('flags');
        $this->assertSame(['possible_duplicate', 'missing_information'], array_column($flags, 'flag'));
        $this->assertCount(2, $flags[0]['transactions']);
        $this->assertEquals($before, FinancialTransaction::query()->get()->toArray());
    }

    public function test_insights_reference_stored_figures_and_are_labelled(): void
    {
        $this->record(['counterparty' => 'مالك المحل']);
        $res = $this->actingAs($this->sme)->getJson(route('ai.finance.insights', $this->query()))->assertOk()
            ->assertJsonPath('ai_generated', true)->assertJsonPath('figures.key', 'monthly');
        $this->assertNotNull($res->json('label'));
        $sent = json_encode($this->fakeAi->calls, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('مالك المحل', $sent);
        $this->assertStringNotContainsString($this->sme->email, $sent);
    }

    public function test_insights_flag_limitations_and_survive_ai_failure(): void
    {
        $this->actingAs($this->sme)->getJson(route('ai.finance.insights', $this->query()))->assertOk()
            ->assertJsonPath('ai_generated', false)->assertJsonCount(1, 'limitations');
        $this->assertSame([], $this->fakeAi->calls);

        $this->record([]);
        $this->fakeAi->failing = true;
        $this->getJson(route('ai.finance.insights', $this->query()))->assertOk()
            ->assertJsonPath('ai_available', false)->assertJsonPath('figures.key', 'monthly');
    }

    public static function roles(): array
    {
        return [
            'assigned advisor' => ['advisor_assigned', 200], 'unassigned advisor' => ['advisor', 403],
            'admin' => ['admin', 200], 'other sme' => ['sme', 403], 'mfi' => ['mfi', 403], 'tech' => ['tech', 403],
        ];
    }

    /** @dataProvider roles */
    public function test_per_role_access(string $who, int $status): void
    {
        $user = match ($who) {
            'advisor_assigned', 'advisor' => $this->userWithRole(UserRole::BeaconAdvisor),
            'admin' => $this->userWithRole(UserRole::BeaconAdmin),
            'sme' => $this->userWithRole(UserRole::SmeUser),
            'mfi' => $this->userWithRole(UserRole::MfiUser),
            'tech' => $this->userWithRole(UserRole::TechnicalAdmin),
        };
        if ($who === 'advisor_assigned') {
            $this->enterprise->advisors()->attach($user->id);
        }
        $this->actingAs($user)->getJson(route('ai.finance.flags', $this->query()))->assertStatus($status);
        $this->getJson(route('ai.finance.insights', $this->query()))->assertStatus($status);
    }

    public function test_inactive_owner_denied(): void
    {
        $this->sme->forceFill(['is_active' => false])->save();
        $this->actingAs($this->sme)->getJson(route('ai.finance.insights', $this->query()))->assertForbidden();
    }
}
