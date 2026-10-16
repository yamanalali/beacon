<?php

namespace Tests\Feature\Finance;

use App\Contracts\ReceiptExtractor;
use App\Contracts\TransactionCategorizer;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\ReceiptExtraction;
use App\Models\User;
use App\Services\Ai\CategorySuggestion;
use App\Services\Ai\ManualReceiptExtractor;
use App\Services\Ai\ManualTransactionCategorizer;
use App\Services\Ai\ReceiptExtractionResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptExtractionTest extends TestCase
{
    use RefreshDatabase;

    private Enterprise $enterprise;

    private User $sme;

    private Document $document;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $this->sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $this->enterprise->members()->attach($this->sme->id);
        $this->actingAs($this->sme)->post(route('enterprises.documents.store', $this->enterprise), [
            'file' => UploadedFile::fake()->create('receipt.jpg', 50, 'image/jpeg'),
        ]);
        $this->document = Document::query()->sole();
    }

    public function test_default_bindings_are_manual(): void
    {
        $this->assertInstanceOf(ManualReceiptExtractor::class, app(ReceiptExtractor::class));
        $this->assertInstanceOf(ManualTransactionCategorizer::class, app(TransactionCategorizer::class));
    }

    public function test_extraction_never_creates_a_transaction_until_user_confirms(): void
    {
        $this->app->bind(ReceiptExtractor::class, fn () => new class implements ReceiptExtractor {
            public function name(): string { return 'fake'; }
            public function extract(Document $document): ReceiptExtractionResult
            {
                return new ReceiptExtractionResult('2026-10-01', '250.00', 'SYP', 'مورد', 0.4);
            }
        });

        $this->actingAs($this->sme)->post(route('documents.extractions.store', $this->document))->assertRedirect();
        $extraction = ReceiptExtraction::query()->sole();
        $this->assertSame('250.00', (string) $extraction->suggested_amount);
        $this->assertTrue($extraction->needs_review);
        $this->assertDatabaseCount('financial_transactions', 0);

        // User corrects the amount before confirming.
        $this->actingAs($this->sme)->post(route('extractions.confirm', $extraction), [
            'type' => 'expense', 'transaction_date' => '2026-10-01', 'amount' => '260', 'currency' => 'SYP',
            'category' => 'rent', 'payment_status' => 'paid',
        ])->assertRedirect();

        $this->assertDatabaseCount('financial_transactions', 1);
        $this->assertDatabaseHas('financial_transactions', ['amount' => '260.00']);
        $this->assertSame('confirmed', $extraction->fresh()->status->value);
        $this->assertNotNull($this->document->fresh()->financial_transaction_id);

        // Cannot confirm twice.
        $this->actingAs($this->sme)->post(route('extractions.confirm', $extraction), [
            'type' => 'expense', 'transaction_date' => '2026-10-01', 'amount' => '260', 'currency' => 'SYP',
            'category' => 'rent', 'payment_status' => 'paid',
        ])->assertStatus(409);
        $this->assertDatabaseCount('financial_transactions', 1);
    }

    public function test_extractor_failure_falls_back_to_manual_entry(): void
    {
        $this->app->bind(ReceiptExtractor::class, fn () => new class implements ReceiptExtractor {
            public function name(): string { return 'broken'; }
            public function extract(Document $document): ReceiptExtractionResult { throw new \RuntimeException('down'); }
        });

        $this->actingAs($this->sme)->post(route('documents.extractions.store', $this->document))->assertRedirect();
        $this->assertNull(ReceiptExtraction::query()->sole()->suggested_amount);
    }

    public function test_categorizer_suggestion_outside_allowed_list_is_ignored(): void
    {
        $this->app->bind(TransactionCategorizer::class, fn () => new class implements TransactionCategorizer {
            public function name(): string { return 'fake'; }
            public function suggest(string $type, ?string $d, ?string $c, array $allowed): ?CategorySuggestion
            {
                return new CategorySuggestion('invented_category', 0.9, false);
            }
        });

        $this->actingAs($this->sme)->post(route('documents.extractions.store', $this->document));
        $extraction = ReceiptExtraction::query()->sole();

        $this->actingAs($this->sme)->get(route('extractions.show', $extraction))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Extractions/Show')->where('category_suggestion', null));
    }

    public function test_reject_keeps_document_and_creates_nothing(): void
    {
        $this->actingAs($this->sme)->post(route('documents.extractions.store', $this->document));
        $extraction = ReceiptExtraction::query()->sole();

        $this->actingAs($this->sme)->post(route('extractions.reject', $extraction))->assertRedirect();
        $this->assertSame('rejected', $extraction->fresh()->status->value);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('documents', 1);
    }

    public function test_role_permissions(): void
    {
        $this->actingAs($this->sme)->post(route('documents.extractions.store', $this->document));
        $extraction = ReceiptExtraction::query()->sole();

        $advisor = User::factory()->create(['role' => UserRole::BeaconAdvisor]);
        $this->enterprise->advisors()->attach($advisor->id);
        $admin = User::factory()->create(['role' => UserRole::BeaconAdmin]);

        // Beacon staff can view but not create/confirm (pending business decision).
        foreach ([$advisor, $admin] as $user) {
            $this->actingAs($user)->get(route('extractions.show', $extraction))->assertOk();
            $this->actingAs($user)->post(route('documents.extractions.store', $this->document))->assertForbidden();
            $this->actingAs($user)->post(route('extractions.reject', $extraction))->assertForbidden();
        }

        foreach ([UserRole::SmeUser, UserRole::BeaconAdvisor, UserRole::MfiUser, UserRole::TechnicalAdmin] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->get(route('extractions.show', $extraction))->assertForbidden();
            $this->actingAs($user)->post(route('documents.extractions.store', $this->document))->assertForbidden();
            $this->actingAs($user)->post(route('extractions.confirm', $extraction), [])->assertForbidden();
        }

        $this->assertSame('pending_review', $extraction->fresh()->status->value);
    }
}
