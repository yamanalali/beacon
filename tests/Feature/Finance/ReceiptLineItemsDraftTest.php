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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** OCR line items are draft suggestions only: nothing is saved before the user confirms. */
class ReceiptLineItemsDraftTest extends TestCase
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
            'file' => UploadedFile::fake()->create('invoice.jpg', 50, 'image/jpeg'),
        ]);
        $this->document = Document::query()->sole();
    }

    private function fakeExtractor(array $items, string $amount, ?string $invoiceNo = null): void
    {
        $this->app->bind(ReceiptExtractor::class, fn () => new class($items, $amount, $invoiceNo) implements ReceiptExtractor
        {
            public function __construct(private array $items, private string $amount, private ?string $no) {}

            public function name(): string
            {
                return 'fake';
            }

            public function extract(Document $document): ReceiptExtractionResult
            {
                return new ReceiptExtractionResult('2024-03-15', $this->amount, null, 'مؤسسة الاختبار', 0.4,
                    rawText: "فاتورة مبيعات\nشاشة عرض 2 3000 6000", items: $this->items, invoiceNumber: $this->no);
            }
        });
    }

    public function test_draft_shows_suggested_items_and_saves_nothing(): void
    {
        $items = [
            ['description' => 'شاشة عرض', 'quantity' => 2, 'unit_price' => 3000, 'total' => 6000],
            ['description' => 'كابل شبكة', 'quantity' => null, 'unit_price' => null, 'total' => 1500.5],
            ['description' => '<b>x</b>', 'quantity' => '3', 'unit_price' => null, 'total' => 'abc'], // strings are not numbers
        ];
        $this->fakeExtractor($items, '7500.50', '4521');

        $this->actingAs($this->sme)->post(route('documents.extractions.store', $this->document))->assertRedirect();
        $extraction = ReceiptExtraction::query()->sole();

        $this->actingAs($this->sme)->get(route('extractions.show', $extraction))
            ->assertInertia(fn (Assert $page) => $page
                ->where('extraction.suggested_items', [
                    ['description' => 'شاشة عرض', 'quantity' => 2, 'unit_price' => 3000, 'total' => 6000],
                    ['description' => 'كابل شبكة', 'quantity' => null, 'unit_price' => null, 'total' => 1500.5],
                    ['description' => 'x', 'quantity' => null, 'unit_price' => null, 'total' => null],
                ])
                ->where('extraction.items_mismatch', false) // one total unread: can't check
                ->where('extraction.suggested_invoice_number', '4521')
                ->where('extraction.suggested_note', 'فاتورة رقم 4521')
                ->where('suggested_transaction_type', 'income'));

        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('transaction_items', 0);
    }

    public function test_mismatch_is_flagged_but_never_blocks(): void
    {
        $this->fakeExtractor([['description' => 'شاشة عرض', 'quantity' => 2, 'unit_price' => 3000, 'total' => 6000]], '6900');
        $this->actingAs($this->sme)->post(route('documents.extractions.store', $this->document));
        $extraction = ReceiptExtraction::query()->sole();

        $this->actingAs($this->sme)->get(route('extractions.show', $extraction))
            ->assertInertia(fn (Assert $page) => $page
                ->where('extraction.items_mismatch', true)
                ->whereType('extraction.suggested_amount_warning', 'string')
                ->where('extraction.suggested_invoice_number', null));
        $this->assertSame('pending_review', $extraction->status->value);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_no_items_is_an_empty_array(): void
    {
        $this->fakeExtractor([], '10');
        $this->actingAs($this->sme)->post(route('documents.extractions.store', $this->document));

        $this->actingAs($this->sme)->get(route('extractions.show', ReceiptExtraction::query()->sole()))
            ->assertInertia(fn (Assert $page) => $page
                ->where('extraction.suggested_items', [])
                ->where('extraction.items_mismatch', false));
    }

    public function test_no_items_and_no_total_gives_null_amount_with_warning(): void
    {
        $this->app->bind(ReceiptExtractor::class, fn () => new class implements ReceiptExtractor
        {
            public function name(): string
            {
                return 'fake';
            }

            public function extract(Document $document): ReceiptExtractionResult
            {
                return new ReceiptExtractionResult('2022-06-08', null, null, 'مؤسسة الاختبار', 0.2, rawText: 'x');
            }
        });
        $this->actingAs($this->sme)->post(route('documents.extractions.store', $this->document));

        $this->actingAs($this->sme)->get(route('extractions.show', ReceiptExtraction::query()->sole()))
            ->assertInertia(fn (Assert $page) => $page
                ->where('extraction.suggested_amount', null)
                ->where('extraction.suggested_items', [])
                ->whereType('extraction.suggested_amount_warning', 'string'));
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_row_qty_times_unit_not_equal_total_warns_but_keeps_values(): void
    {
        $this->fakeExtractor([
            ['description' => 'حاسوب محمول', 'quantity' => 2, 'unit_price' => 12000, 'total' => 25000],
            ['description' => 'فأرة', 'quantity' => 1, 'unit_price' => 4000, 'total' => 4000],
        ], '29000');
        $this->actingAs($this->sme)->post(route('documents.extractions.store', $this->document));

        $this->actingAs($this->sme)->get(route('extractions.show', ReceiptExtraction::query()->sole()))
            ->assertInertia(fn (Assert $page) => $page
                ->where('extraction.suggested_items.0.total', 25000)
                ->where('extraction.items_mismatch', true)
                ->whereType('extraction.suggested_amount_warning', 'string')
                ->where('extraction.suggested_note', null));
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_consistent_items_matching_total_have_no_warning(): void
    {
        $this->fakeExtractor([
            ['description' => 'حاسوب محمول', 'quantity' => 2, 'unit_price' => 12000, 'total' => 24000],
            ['description' => 'فأرة لاسلكية', 'quantity' => 1, 'unit_price' => 4000, 'total' => 4000],
            ['description' => 'طابعة ملونة', 'quantity' => 1, 'unit_price' => 6000, 'total' => 6000],
            ['description' => 'شاشة عرض', 'quantity' => 3, 'unit_price' => 2500, 'total' => 7500],
        ], '41500', '1002');
        $this->actingAs($this->sme)->post(route('documents.extractions.store', $this->document));

        $this->actingAs($this->sme)->get(route('extractions.show', ReceiptExtraction::query()->sole()))
            ->assertInertia(fn (Assert $page) => $page
                ->where('extraction.items_mismatch', false)
                ->where('extraction.suggested_amount_warning', null)
                ->where('extraction.suggested_note', 'فاتورة رقم 1002'));
    }
}
