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

class ReceiptCategorySuggestionTest extends TestCase
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

    private function extractWithText(string $raw, string $counterparty = 'بيتزا 1'): ReceiptExtraction
    {
        $this->app->bind(ReceiptExtractor::class, fn () => new class($raw, $counterparty) implements ReceiptExtractor {
            public function __construct(private string $raw, private string $counterparty) {}
            public function name(): string { return 'fake'; }
            public function extract(Document $document): ReceiptExtractionResult
            {
                return new ReceiptExtractionResult('2021-01-22', '21.54', null, $this->counterparty, 0.8, rawText: $this->raw);
            }
        });
        $this->actingAs($this->sme)->post(route('documents.extractions.store', $this->document))->assertRedirect();

        return ReceiptExtraction::query()->sole();
    }

    public function test_line_items_drive_category_suggestion(): void
    {
        $extraction = $this->extractWithText("سلطة الجبنة 17.24\nعصير البطيخ 4.30\nالمبلغ المستحق 21.54");
        $this->assertSame('meals', $extraction->suggested_category);

        $this->actingAs($this->sme)->get(route('extractions.show', $extraction))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('suggested_transaction_type', 'expense')
                ->where('category_suggestion.category', 'meals')
                ->where('category_suggestion.needs_review', true));
    }

    public function test_unknown_text_gives_no_suggestion(): void
    {
        $extraction = $this->extractWithText("رقم 55\nالمجموع 21.54", 'محل عام');

        $this->actingAs($this->sme)->get(route('extractions.show', $extraction))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('category_suggestion', null));
    }

    public function test_food_text_without_food_category_in_config_gives_no_suggestion(): void
    {
        $expense = config('bookkeeping.categories.expense');
        unset($expense['meals']);
        config(['bookkeeping.categories.expense' => $expense]);

        $extraction = $this->extractWithText("سلطة الجبنة 17.24\nعصير البطيخ 4.30");

        $this->actingAs($this->sme)->get(route('extractions.show', $extraction))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('category_suggestion', null));
    }

    public function test_utilities_receipt_maps_to_configured_key(): void
    {
        $extraction = $this->extractWithText("فاتورة كهرباء\nالمبلغ 21.54");

        $this->actingAs($this->sme)->get(route('extractions.show', $extraction))
            ->assertInertia(fn ($page) => $page->where('category_suggestion.category', 'utilities'));
    }
}
