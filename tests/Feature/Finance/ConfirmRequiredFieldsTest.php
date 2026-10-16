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

/**
 * The server never fills currency/date/category/type on confirm:
 * each must come from the user, even when the extraction suggested one.
 */
class ConfirmRequiredFieldsTest extends TestCase
{
    use RefreshDatabase;

    private User $sme;

    private ReceiptExtraction $extraction;

    private const VALID = [
        'type' => 'expense', 'transaction_date' => '2021-01-22', 'amount' => '21.54',
        'currency' => 'USD', 'category' => 'other_expense', 'payment_status' => 'paid',
    ];

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
        // Extraction WITH suggestions, to prove they are not used as fallbacks.
        $this->app->bind(ReceiptExtractor::class, fn () => new class implements ReceiptExtractor {
            public function name(): string { return 'fake'; }
            public function extract(Document $document): ReceiptExtractionResult
            {
                return new ReceiptExtractionResult('2021-01-22', '21.54', 'SYP', 'بيتزا 1', 0.8);
            }
        });
        $this->actingAs($this->sme)->post(route('documents.extractions.store', Document::query()->sole()));
        $this->extraction = ReceiptExtraction::query()->sole();
    }

    public static function missingFields(): array
    {
        return [
            'currency' => ['currency'], 'date' => ['transaction_date'],
            'category' => ['category'], 'type' => ['type'],
        ];
    }

    /** @dataProvider missingFields */
    public function test_missing_field_is_rejected_and_nothing_created(string $field): void
    {
        foreach ([fn ($d) => array_diff_key($d, [$field => 1]), fn ($d) => [$field => ''] + $d] as $mutate) {
            $this->actingAs($this->sme)
                ->postJson(route('extractions.confirm', $this->extraction), $mutate(self::VALID))
                ->assertStatus(422)->assertJsonValidationErrors($field);
        }
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertSame('pending_review', $this->extraction->fresh()->status->value);
    }

    public static function invalidValues(): array
    {
        return [
            'type not in enum' => ['type', 'gift'],
            'category of another type' => ['category', 'general_sales'],
            'category not configured' => ['category', 'meals_invented'],
            'currency not allowed' => ['currency', 'EUR'],
            'invalid date' => ['transaction_date', '2021-13-45'],
            'future date' => ['transaction_date', '2999-01-01'],
        ];
    }

    /** @dataProvider invalidValues */
    public function test_invalid_value_is_rejected(string $field, string $value): void
    {
        $this->actingAs($this->sme)
            ->postJson(route('extractions.confirm', $this->extraction), [$field => $value] + self::VALID)
            ->assertStatus(422)->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_full_valid_input_creates_transaction_with_user_values(): void
    {
        $this->actingAs($this->sme)->post(route('extractions.confirm', $this->extraction), self::VALID)->assertRedirect();

        $this->assertDatabaseCount('financial_transactions', 1);
        // User-chosen USD wins over the suggested SYP / base currency.
        $this->assertDatabaseHas('financial_transactions', [
            'type' => 'expense', 'currency' => 'USD', 'category' => 'other_expense', 'amount' => '21.54',
        ]);
        $this->assertSame('confirmed', $this->extraction->fresh()->status->value);
    }
}
