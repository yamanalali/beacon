<?php

namespace Tests\Feature\Ai;

use App\Ai\Voice\SpeechToText;
use App\Ai\Voice\VoiceDraftParser;
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

/** suggested_date_warning: old (> ai.review.old_date_days) or future dates warn, never block. */
class SuggestedDateWarningTest extends TestCase
{
    use AiTestHelpers, RefreshDatabase;

    private Enterprise $enterprise;

    private User $sme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFakeAi();
        Storage::fake('local');
        config(['ai.voice.enabled' => true, 'ai.review.old_date_days' => 365]);
        $this->enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $this->sme = User::factory()->create(['role' => UserRole::SmeUser]);
        $this->enterprise->members()->attach($this->sme->id);
    }

    public static function dates(): array
    {
        return [
            'old' => [fn () => now()->subDays(400)->toDateString(), true],
            'future' => [fn () => now()->addDays(3)->toDateString(), true],
            'recent' => [fn () => now()->subDays(10)->toDateString(), false],
        ];
    }

    /** @dataProvider dates */
    public function test_voice_draft_date_warning(\Closure $date, bool $warns): void
    {
        $d = $date();
        $this->app->instance(SpeechToText::class, new class implements SpeechToText
        {
            public function name(): string
            {
                return 'whisper';
            }

            public function transcribe(string $path, string $mimeType): ?string
            {
                return 'دفعت 1000';
            }
        });
        $this->app->instance(VoiceDraftParser::class, new class($d) extends VoiceDraftParser
        {
            public function __construct(private string $d) {}

            public function parse(string $text): array
            {
                return ['amount' => '1000', 'date' => $this->d, 'counterparty' => null, 'currency' => null, 'note' => null,
                    'items' => [], 'items_mismatch' => false, 'amount_warning' => null, 'confidence' => 0.5];
            }
        });

        $r = $this->actingAs($this->sme)->post(route('ai.voice-drafts.store', $this->enterprise),
            ['audio' => UploadedFile::fake()->create('note.webm', 40, 'audio/webm')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('draft.suggested_date', $d);
        $warns ? $this->assertIsString($r->json('draft.suggested_date_warning'))
            : $this->assertNull($r->json('draft.suggested_date_warning'));
        $this->assertArrayHasKey('suggested_date_warning', $r->json('draft'));
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    /** @dataProvider dates */
    public function test_receipt_extraction_date_warning(\Closure $date, bool $warns): void
    {
        $d = $date();
        $this->actingAs($this->sme)->post(route('enterprises.documents.store', $this->enterprise), [
            'file' => UploadedFile::fake()->create('invoice.jpg', 50, 'image/jpeg'),
        ]);
        $document = Document::query()->sole();
        $this->app->bind(ReceiptExtractor::class, fn () => new class($d) implements ReceiptExtractor
        {
            public function __construct(private string $d) {}

            public function name(): string
            {
                return 'fake';
            }

            public function extract(Document $document): ReceiptExtractionResult
            {
                return new ReceiptExtractionResult($this->d, '1000', null, 'محل', 0.4, rawText: 'فاتورة 1000');
            }
        });
        $this->actingAs($this->sme)->post(route('documents.extractions.store', $document))->assertRedirect();
        $extraction = ReceiptExtraction::query()->sole();

        $this->actingAs($this->sme)->get(route('extractions.show', $extraction))
            ->assertInertia(fn (Assert $page) => $warns
                ? $page->whereType('extraction.suggested_date_warning', 'string')
                : $page->where('extraction.suggested_date_warning', null));
        $this->assertSame('pending_review', $extraction->status->value);
    }
}
