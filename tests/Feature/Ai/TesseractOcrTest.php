<?php

namespace Tests\Feature\Ai;

use App\Ai\Finance\AiReceiptExtractor;
use App\Ai\Ocr\ReceiptTextParser;
use App\Ai\Ocr\TesseractReceiptExtractor;
use App\Contracts\ReceiptExtractor;
use App\Enums\UserRole;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\ReceiptExtraction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TesseractOcrTest extends TestCase
{
    use AiTestHelpers, RefreshDatabase;

    private const SAMPLE = "سوبرماركت الأمل\nدمشق - المزة\nالتاريخ: ١٥/١٠/٢٠٢٦\nخبز 2 × 1,500\nحليب 7,000\nالإجمالي: ١٠٬٠٠٠ ل.س\nشكراً لزيارتكم";

    private Enterprise $enterprise;

    private User $sme;

    protected function setUp(): void
    {
        parent::setUp();
        // Static `--list-langs` cache must not leak in from other OCR tests (order-dependent flake).
        TesseractReceiptExtractor::flushLanguageCache();
        Storage::fake('local');
        $this->enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $this->sme = $this->userWithRole(UserRole::SmeUser);
        $this->enterprise->members()->attach($this->sme->id);
    }

    protected function tearDown(): void
    {
        TesseractReceiptExtractor::flushLanguageCache();
        parent::tearDown();
    }

    private function upload(string $name = 'r.jpg'): Document
    {
        $this->actingAs($this->sme)->post(route('enterprises.documents.store', $this->enterprise), ['file' => UploadedFile::fake()->image($name)]);

        return Document::query()->latest('id')->first();
    }

    /** Tesseract extractor whose Process is replaced by a shell command printing $out. */
    private function tesseractWith(?string $out, int $exit = 0): TesseractReceiptExtractor
    {
        return new class(new ReceiptTextParser, $out, $exit) extends TesseractReceiptExtractor
        {
            public array $commands = [];

            public function __construct(ReceiptTextParser $p, private ?string $out, private int $exit)
            {
                parent::__construct($p);
            }

            protected function makeProcess(array $command): Process
            {
                $this->commands[] = $command;

                return new Process(['php', '-r', 'fwrite(STDOUT, base64_decode($argv[1])); exit((int) $argv[2]);', base64_encode((string) $this->out), (string) $this->exit]);
            }

            protected function makeLanguageProcess(array $command): Process
            {
                // Independent of the host tesseract install: both required languages present.
                return new Process(['php', '-r', 'echo "ara\neng\n";']);
            }
        };
    }

    public function test_parser_extracts_amount_date_vendor_and_currency(): void
    {
        $r = (new ReceiptTextParser)->parse(self::SAMPLE);
        $this->assertSame('10000', $r->amount);
        $this->assertSame('2026-10-15', $r->transactionDate);
        $this->assertSame('سوبرماركت الأمل', $r->counterparty);
        $this->assertSame('SYP', $r->currency);
        $this->assertTrue($r->needsReview);
        $this->assertFalse($r->failed);
        $this->assertLessThanOrEqual(0.5, $r->confidence);
    }

    public function test_parser_handles_english_iso_date_and_empty_text(): void
    {
        $r = (new ReceiptTextParser)->parse("ACME Store\n2026-09-03\nTotal: 45.50 USD");
        $this->assertSame(['45.5', '2026-09-03', 'ACME Store', 'USD'], [$r->amount, $r->transactionDate, $r->counterparty, $r->currency]);
        $this->assertTrue((new ReceiptTextParser)->parse("  \n ")->failed);
    }

    public function test_fake_process_output_becomes_pending_suggestion_and_no_transaction_until_confirmed(): void
    {
        $doc = $this->upload();
        $ext = $this->tesseractWith(self::SAMPLE);
        $this->app->instance(ReceiptExtractor::class, $ext);

        $this->actingAs($this->sme)->post(route('documents.extractions.store', $doc))->assertRedirect();
        $e = ReceiptExtraction::query()->sole();
        $this->assertSame('tesseract', $e->extractor);
        $this->assertSame('10000.00', $e->suggested_amount);
        $this->assertSame('2026-10-15', $e->suggested_date->toDateString());
        $this->assertSame('سوبرماركت الأمل', $e->suggested_counterparty);
        $this->assertTrue((bool) $e->needs_review);
        $this->assertSame('pending_review', $e->status->value);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertSame(['-l', 'ara+eng'], array_slice($ext->commands[0], 3, 2));

        $this->get(route('extractions.show', $e))->assertInertia(fn ($p) => $p->component('Extractions/Show', false)
            ->where('extraction.extractor', 'tesseract')->where('extraction.is_manual', false)->where('can_review', true));
    }

    public function test_missing_binary_is_graceful(): void
    {
        config(['ai.ocr.tesseract_path' => '/nonexistent/tesseract']);
        $doc = $this->upload();
        $this->app->bind(ReceiptExtractor::class, TesseractReceiptExtractor::class);
        $this->assertTrue(app(ReceiptExtractor::class)->extract($doc)->failed);

        // Flow still works: an empty suggestion for manual entry.
        $this->actingAs($this->sme)->post(route('documents.extractions.store', $doc))->assertRedirect();
        $e = ReceiptExtraction::query()->sole();
        $this->assertNull($e->suggested_amount);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_nonzero_exit_and_pdf_are_graceful(): void
    {
        $this->assertTrue($this->tesseractWith('x', 1)->extract($this->upload())->failed);
        $this->actingAs($this->sme)->post(route('enterprises.documents.store', $this->enterprise), ['file' => UploadedFile::fake()->create('r.pdf', 10, 'application/pdf')]);
        $pdf = Document::query()->where('mime_type', 'application/pdf')->first();
        $ext = $this->tesseractWith(self::SAMPLE);
        $this->assertTrue($ext->extract($pdf)->failed);
        $this->assertSame([], $ext->commands);
    }

    public function test_local_tesseract_needs_no_data_sharing_approval(): void
    {
        $this->approveAiSharing(false);
        $this->assertFalse($this->tesseractWith(self::SAMPLE)->extract($this->upload())->failed);
    }

    public function test_external_ocr_provider_still_requires_assert_can_share(): void
    {
        $this->setUpFakeAi();
        $this->approveAiSharing(false);
        $doc = $this->upload();
        $this->app->bind(ReceiptExtractor::class, AiReceiptExtractor::class);

        $this->actingAs($this->sme)->post(route('documents.extractions.store', $doc))->assertRedirect();
        $this->assertSame([], $this->fakeAi->calls, 'External OCR provider was called without approval.');
        $this->assertNull(ReceiptExtraction::query()->sole()->suggested_amount);

        $this->approveAiSharing(true);
        app(ReceiptExtractor::class)->extract($doc);
        $this->assertCount(1, $this->fakeAi->calls);
    }

    /** Evaluate config/ai.php with explicit env values, independent of the local .env. */
    private function aiConfigWith(array $vars): array
    {
        $saved = [];
        foreach ($vars as $k => $v) {
            $saved[$k] = [$_ENV[$k] ?? null, $_SERVER[$k] ?? null, getenv($k)];
            if ($v === null) {
                unset($_ENV[$k], $_SERVER[$k]);
                putenv($k);
            } else {
                $_ENV[$k] = $_SERVER[$k] = $v;
                putenv("$k=$v");
            }
        }
        try {
            return require base_path('config/ai.php');
        } finally {
            foreach ($saved as $k => [$e, $s, $g]) {
                $e === null ? Arr::forget($_ENV, $k) : $_ENV[$k] = $e;
                $s === null ? Arr::forget($_SERVER, $k) : $_SERVER[$k] = $s;
                $g === false ? putenv($k) : putenv("$k=$g");
            }
        }
    }

    public function test_local_env_defaults_to_tesseract(): void
    {
        $this->assertSame('tesseract', $this->aiConfigWith(['APP_ENV' => 'local', 'AI_OCR_DRIVER' => null])['ocr']['driver']);
        $this->assertNull($this->aiConfigWith(['APP_ENV' => 'testing', 'AI_OCR_DRIVER' => null])['ocr']['driver']);
        $this->assertSame('manual', $this->aiConfigWith(['APP_ENV' => 'local', 'AI_OCR_DRIVER' => 'manual'])['ocr']['driver']);
    }

    public function test_empty_env_values_fall_back_to_defaults(): void
    {
        $c = $this->aiConfigWith(['APP_ENV' => 'local', 'AI_OCR_DRIVER' => '', 'TESSERACT_PATH' => '', 'TESSERACT_PSM' => '',
            'AI_OCR_PREPROCESS' => '', 'AI_STT_DRIVER' => '', 'WHISPER_PATH' => '', 'WHISPER_MODEL' => '', 'TESSERACT_TESSDATA_DIR' => '']);
        $this->assertSame('tesseract', $c['ocr']['driver']);
        $this->assertSame('tesseract', $c['ocr']['tesseract_path']);
        $this->assertSame('6', $c['ocr']['psm']);
        $this->assertTrue($c['ocr']['preprocess']);
        $this->assertSame('whisper', $c['voice']['stt_driver']);
        $this->assertSame('whisper-cli', $c['voice']['whisper_path']);
        $this->assertSame('', $c['voice']['whisper_model']);
        $this->assertSame('large-v3-turbo', $c['voice']['whisper_default_model']);
        $this->assertEmpty($c['ocr']['tessdata_dir'], 'tessdata dir stays optional/empty');

        // explicit "false" is still respected
        $this->assertFalse($this->aiConfigWith(['APP_ENV' => 'local', 'AI_OCR_PREPROCESS' => 'false'])['ocr']['preprocess']);
    }
}
