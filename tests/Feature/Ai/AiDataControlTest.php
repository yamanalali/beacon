<?php

namespace Tests\Feature\Ai;

use App\Ai\Finance\AiReceiptExtractor;
use App\Ai\Finance\AiTransactionCategorizer;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Section 54: no outbound AI provider call unless DataControl approves sharing. */
class AiDataControlTest extends TestCase
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

    public static function decisions(): array
    {
        return ['allowed' => [true], 'denied' => [false]];
    }

    /** @dataProvider decisions */
    public function test_assistant(bool $allowed): void
    {
        $this->approveAiSharing($allowed);
        $this->article();
        $this->actingAs($this->sme)->postJson(route('ai.assistant.ask'), ['question' => 'كيف أسجل المصاريف؟'])
            ->assertOk()->assertJsonPath('supported', $allowed)->assertJsonPath('can_escalate', true);
        $this->assertCount($allowed ? 1 : 0, $this->fakeAi->calls);
    }

    /** @dataProvider decisions */
    public function test_receipt_extractor(bool $allowed): void
    {
        $this->approveAiSharing($allowed);
        $this->actingAs($this->sme)->post(route('enterprises.documents.store', $this->enterprise), ['file' => UploadedFile::fake()->image('r.jpg')]);
        $r = app(AiReceiptExtractor::class)->extract(Document::query()->sole());
        $this->assertSame(! $allowed, $r->failed);
        $this->assertCount($allowed ? 1 : 0, $this->fakeAi->calls);
    }

    /** @dataProvider decisions */
    public function test_categorizer(bool $allowed): void
    {
        $this->approveAiSharing($allowed);
        $s = app(AiTransactionCategorizer::class)->suggest('expense', 'دفعة إيجار المحل', null, config('bookkeeping.categories.expense'));
        $this->assertSame($allowed ? 'rent' : null, $s?->category);
        $this->assertCount($allowed ? 1 : 0, $this->fakeAi->calls);
    }

    /** @dataProvider decisions */
    public function test_insights(bool $allowed): void
    {
        $this->actingAs($this->sme)->post(route('enterprises.transactions.store', $this->enterprise), [
            'transaction_date' => '2026-09-10', 'currency' => 'SYP', 'payment_status' => 'paid',
            'type' => 'expense', 'category' => 'rent', 'amount' => '100',
        ])->assertSessionHasNoErrors();
        $this->fakeAi->calls = [];
        $this->approveAiSharing($allowed);
        $this->getJson(route('ai.finance.insights', ['enterprise' => $this->enterprise, 'from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk()->assertJsonPath('ai_available', $allowed)->assertJsonPath('ai_generated', $allowed);
        $this->assertCount($allowed ? 1 : 0, $this->fakeAi->calls);
    }

    public function test_fields_outside_approved_list_are_denied(): void
    {
        config(['operations.data_processors.ai_assistant.allowed_data' => ['user_question']]);
        $this->article();
        $this->actingAs($this->sme)->postJson(route('ai.assistant.ask'), ['question' => 'كيف أسجل المصاريف؟'])
            ->assertOk()->assertJsonPath('supported', false);
        $this->assertSame([], $this->fakeAi->calls);
    }
}
