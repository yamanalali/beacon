<?php

namespace Tests\Feature\Finance;

use App\Contracts\ReceiptExtractor;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\ReceiptExtraction;
use App\Models\User;
use App\Services\Ai\ReceiptExtractionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransactionTypeSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private User $sme;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $this->sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $enterprise->members()->attach($this->sme->id);
        $this->actingAs($this->sme)->post(route('enterprises.documents.store', $enterprise), [
            'file' => UploadedFile::fake()->create('receipt.jpg', 50, 'image/jpeg'),
        ]);
        $this->document = Document::query()->sole();
    }

    private function fakeExtractor(ReceiptExtractionResult $result): void
    {
        $this->app->bind(ReceiptExtractor::class, fn () => new class($result) implements ReceiptExtractor {
            public function __construct(private ReceiptExtractionResult $r) {}
            public function name(): string { return 'fake'; }
            public function extract(Document $document): ReceiptExtractionResult { return $this->r; }
        });
    }

    private function startAndShow()
    {
        $this->actingAs($this->sme)->post(route('documents.extractions.store', $this->document))->assertRedirect();
        $extraction = ReceiptExtraction::query()->sole();

        return [$extraction, $this->actingAs($this->sme)->get(route('extractions.show', $extraction))->assertOk()];
    }

    public function test_receipt_text_suggests_expense_with_expense_categories_and_saves_nothing(): void
    {
        $this->fakeExtractor(new ReceiptExtractionResult('2026-10-01', '250.00', 'SYP', 'مورد', 0.4, rawText: "مورد\nالإجمالي 250"));
        [, $res] = $this->startAndShow();
        $res->assertInertia(fn ($p) => $p->component('Extractions/Show')
            ->where('suggested_transaction_type', 'expense')
            ->where('type', 'expense')
            ->where('transaction_type_options', [['value' => 'expense', 'label' => 'مصروف'], ['value' => 'income', 'label' => 'دخل آخر']]));
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_receipt_of_payment_suggests_income_and_income_type(): void
    {
        $this->fakeExtractor(new ReceiptExtractionResult('2026-10-01', '500.00', 'SYP', 'أحمد', 0.4, rawText: "سند قبض\nدفعة من زبون\n500"));
        [$extraction, $res] = $this->startAndShow();
        $res->assertInertia(fn ($p) => $p->where('suggested_transaction_type', 'income')->where('type', 'income'));
        $this->assertDatabaseCount('financial_transactions', 0);

        // Explicit ?type= still wins for the category list; suggestion unchanged.
        $this->actingAs($this->sme)->get(route('extractions.show', [$extraction, 'type' => 'expense']))
            ->assertInertia(fn ($p) => $p->where('type', 'expense')->where('suggested_transaction_type', 'income'));

        // User can override the suggestion on confirm.
        $this->actingAs($this->sme)->post(route('extractions.confirm', $extraction), [
            'type' => 'income', 'transaction_date' => '2026-10-01', 'amount' => '500', 'currency' => 'SYP',
            'category' => 'other_income', 'payment_status' => 'paid',
        ])->assertRedirect();
        $this->assertDatabaseHas('financial_transactions', ['type' => 'income', 'amount' => '500.00']);
    }

    public function test_ocr_unavailable_gives_null_suggestion(): void
    {
        $this->fakeExtractor(new ReceiptExtractionResult(failed: true));
        [, $res] = $this->startAndShow();
        $res->assertInertia(fn ($p) => $p->where('suggested_transaction_type', null)
            ->where('extraction.suggested_amount', null)->where('type', 'expense'));
    }
}
