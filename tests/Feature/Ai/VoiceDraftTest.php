<?php

namespace Tests\Feature\Ai;

use App\Ai\Voice\ProviderTranscriber;
use App\Ai\Voice\SpeechToText;
use App\Ai\Voice\VoiceDraftParser;
use App\Ai\Voice\WhisperCppTranscriber;
use App\Contracts\TransactionCategorizer;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Enterprise;
use App\Models\User;
use App\Services\Ai\CategorySuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class VoiceDraftTest extends TestCase
{
    use AiTestHelpers, RefreshDatabase;

    private Enterprise $enterprise;

    private User $sme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpFakeAi();
        config(['ai.voice.enabled' => true]);
        $this->enterprise = Enterprise::query()->create(['business_name' => 'منشأة', 'location' => 'damascus']);
        $this->sme = $this->userWithRole(UserRole::SmeUser);
        $this->enterprise->members()->attach($this->sme->id);
    }

    private function audio(): UploadedFile
    {
        return UploadedFile::fake()->create('note.webm', 40, 'audio/webm');
    }

    /** Local STT stub that records the path it saw and whether the file existed. */
    private function stubStt(?string $text): object
    {
        $stub = new class($text) implements SpeechToText
        {
            public ?string $seenPath = null;

            public bool $existed = false;

            public function __construct(private ?string $text) {}

            public function name(): string
            {
                return 'whisper';
            }

            public function transcribe(string $path, string $mimeType): ?string
            {
                $this->seenPath = $path;
                $this->existed = is_file($path);

                return $this->text;
            }
        };
        $this->app->instance(SpeechToText::class, $stub);

        return $stub;
    }

    private function send(?User $u = null)
    {
        return $this->actingAs($u ?? $this->sme)->post(route('ai.voice-drafts.store', $this->enterprise), ['audio' => $this->audio()], ['Accept' => 'application/json']);
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

    public function test_disabled_by_default_returns_404(): void
    {
        config(['ai.voice.enabled' => false]);
        $this->stubStt('دفعت 100');
        $this->send()->assertNotFound();
        // Default pinned with AI_VOICE_ENABLED unset, independent of the local .env (as in #60).
        $this->assertFalse((bool) $this->aiConfigWith(['AI_VOICE_ENABLED' => null])['voice']['enabled']);
    }

    public function test_roles_follow_transaction_create_policy(): void
    {
        $this->stubStt('دفعت 100');
        $this->send($this->userWithRole(UserRole::MfiUser))->assertForbidden();
        $this->send($this->userWithRole(UserRole::SmeUser))->assertForbidden(); // not a member
        $this->send()->assertOk();
    }

    public function test_validation_rejects_non_audio_and_oversize(): void
    {
        $this->stubStt('x');
        $this->actingAs($this->sme)->postJson(route('ai.voice-drafts.store', $this->enterprise), ['audio' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertUnprocessable();
        config(['ai.voice.max_kb' => 10]);
        $this->actingAs($this->sme)->postJson(route('ai.voice-drafts.store', $this->enterprise), ['audio' => $this->audio()])->assertUnprocessable();
    }

    public function test_success_returns_draft_shape_deletes_audio_and_never_saves(): void
    {
        $stub = $this->stubStt('دفعت اليوم 5,000 ليرة لمحل الأمل أجار');
        $res = $this->send()->assertOk()
            ->assertJsonStructure([
                'enterprise' => ['public_id', 'business_name'],
                'draft' => ['source', 'extractor', 'is_manual', 'needs_review', 'confidence', 'suggested_date', 'suggested_amount', 'suggested_currency', 'suggested_counterparty', 'suggested_transaction_type', 'raw_text'],
                'type', 'transaction_type_options' => [['value', 'label']], 'category_suggestion', 'options' => ['types', 'payment_statuses', 'currencies', 'default_currency', 'categories'],
                'ai_available', 'message', 'confirm' => ['method', 'url'],
            ])
            ->assertJsonPath('draft.source', 'voice')->assertJsonPath('draft.needs_review', true)
            ->assertJsonPath('draft.suggested_amount', '5000')->assertJsonPath('draft.suggested_transaction_type', 'expense')
            ->assertJsonPath('draft.suggested_currency', 'SYP')->assertJsonPath('draft.suggested_date', now()->toDateString())
            ->assertJsonPath('draft.suggested_counterparty', 'الأمل')->assertJsonPath('ai_available', true)
            ->assertJsonPath('confirm.url', route('enterprises.transactions.store', $this->enterprise));

        $this->assertSame($res->json('options.types'), $res->json('transaction_type_options'));
        $this->assertTrue($stub->existed);
        $this->assertFileDoesNotExist($stub->seenPath);
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('receipt_extractions', 0);
        $json = $res->json();
        array_walk_recursive($json, fn ($v, $k) => $this->assertFalse(is_int($v) && in_array($k, ['id', 'enterprise_id', 'user_id'], true)));
        $this->assertArrayNotHasKey('id', $res->json('enterprise'));
    }

    public function test_yaman_rent_sentence_draft(): void
    {
        $this->stubStt('دفعت اجار محل 50 الف ليرة سوريا لمدة ثلاثة اشهر');
        $this->send()->assertOk()
            ->assertJsonPath('draft.suggested_amount', '50000')
            ->assertJsonPath('draft.suggested_currency', 'SYP')
            ->assertJsonPath('draft.suggested_note', 'لمدة ثلاثة اشهر')
            ->assertJsonPath('draft.suggested_transaction_type', 'expense')
            ->assertJsonPath('category_suggestion.category', 'rent');
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_yaman_monthly_rent_sentence_gives_one_item_and_ambiguity_warning(): void
    {
        $this->stubStt('دفعت ايجار محل 3000 الاف لمدة 3 اشهر كل شهر الف');
        $r = $this->send()->assertOk()
            ->assertJsonPath('draft.suggested_amount', '3000')
            ->assertJsonPath('draft.suggested_transaction_type', 'expense')
            ->assertJsonPath('category_suggestion.category', 'rent')
            ->assertJsonPath('draft.items_mismatch', false)
            ->assertJsonPath('draft.suggested_items', [['description' => 'إيجار محل (شهري)', 'quantity' => 3, 'unit_price' => 1000, 'total' => 3000]]);
        $this->assertStringContainsString('لمدة 3 اشهر', $r->json('draft.suggested_note'));
        $this->assertNotNull($r->json('draft.suggested_amount_warning'));
        $this->assertDatabaseCount('financial_transactions', 0);
        $this->assertDatabaseCount('transaction_items', 0);
    }

    public function test_multiple_goods_sentence_gives_separate_items(): void
    {
        $this->stubStt('اشتريت 2 كرسي بـ500 وطاولة بـ1000');
        $this->send()->assertOk()
            ->assertJsonPath('draft.suggested_amount', '2000')
            ->assertJsonPath('draft.items_mismatch', false)
            ->assertJsonPath('draft.suggested_amount_warning', null)
            ->assertJsonPath('draft.suggested_items', [
                ['description' => 'كرسي', 'quantity' => 2, 'unit_price' => 500, 'total' => 1000],
                ['description' => 'طاولة', 'quantity' => 1, 'unit_price' => 1000, 'total' => 1000],
            ]);
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_clear_sentence_has_no_items_and_no_warning(): void
    {
        $this->stubStt('دفعت اجار محل 50 الف ليرة سوريا لمدة ثلاثة اشهر');
        $this->send()->assertOk()
            ->assertJsonPath('draft.suggested_items', [])
            ->assertJsonPath('draft.items_mismatch', false)
            ->assertJsonPath('draft.suggested_amount_warning', null);
    }

    public function test_yaman_real_windows_transcript_draft(): void
    {
        $this->stubStt('دفعت خمسين ألف ليرائي جر محل اليوم عن ثلاث شهور');
        $this->send()->assertOk()
            ->assertJsonPath('draft.suggested_amount', '50000')
            ->assertJsonPath('draft.suggested_currency', 'SYP')
            ->assertJsonPath('draft.suggested_counterparty', null)
            ->assertJsonPath('draft.suggested_note', 'عن ثلاث شهور')
            ->assertJsonPath('draft.suggested_transaction_type', 'expense')
            ->assertJsonPath('category_suggestion.category', 'rent');
        $this->assertDatabaseCount('financial_transactions', 0);
    }

    public function test_no_spoken_currency_suggests_business_base_currency(): void
    {
        config(['bookkeeping.default_currency' => 'USD']);
        $this->stubStt('دفعت مية اجار');
        $this->send()->assertOk()->assertJsonPath('draft.suggested_currency', 'USD')->assertJsonPath('draft.suggested_currency_source', 'default');
    }

    public function test_no_spoken_currency_and_no_base_currency_falls_back_to_syp(): void
    {
        config(['bookkeeping.default_currency' => null]);
        $this->stubStt('دفعت مية اجار');
        $this->send()->assertOk()->assertJsonPath('draft.suggested_currency', 'SYP')->assertJsonPath('draft.suggested_currency_source', 'default');
    }

    public function test_spoken_currency_wins_over_base(): void
    {
        config(['bookkeeping.default_currency' => 'USD']);
        $this->stubStt('دفعت مية ليرا اجار');
        $this->send()->assertOk()->assertJsonPath('draft.suggested_currency', 'SYP')->assertJsonPath('draft.suggested_currency_source', 'spoken');
    }

    public function test_voice_fuzzy_variants_end_to_end(): void
    {
        foreach (['دفعت مية ليرا جر محل', 'دفعت مية ليرائي جر محل', 'دفعت مية ليره ئي جر', 'دفعت مية لير جر محل'] as $t) {
            $this->stubStt($t);
            $this->send()->assertOk()
                ->assertJsonPath('draft.suggested_currency', 'SYP')
                ->assertJsonPath('draft.suggested_currency_source', 'spoken')
                ->assertJsonPath('draft.suggested_transaction_type', 'expense')
                ->assertJsonPath('category_suggestion.category', 'rent');
        }
    }

    public function test_encoding_failure_returns_clear_message(): void
    {
        $w = new class extends WhisperCppTranscriber
        {
            protected function makeProcess(array $command): Process
            {
                return new Process(['php', '-r', 'echo "?????? ?????? ??????";']);
            }
        };
        $this->app->instance(SpeechToText::class, $w);
        $this->send()->assertOk()->assertJsonPath('ai_available', false)->assertJsonPath('draft.raw_text', null)
            ->assertJsonPath('draft.needs_review', true)->assertJsonPath('message', fn ($m) => str_contains($m, 'ترميز'));
    }

    public function test_unavailable_stt_deletes_audio_and_returns_empty_draft(): void
    {
        $stub = $this->stubStt(null);
        $this->send()->assertOk()->assertJsonPath('ai_available', false)
            ->assertJsonPath('draft.suggested_amount', null)->assertJsonPath('draft.raw_text', null);
        $this->assertFileDoesNotExist($stub->seenPath);
    }

    public function test_external_provider_denied_sharing_makes_no_provider_call(): void
    {
        $this->approveAiSharing(false);
        $this->app->instance(SpeechToText::class, app(ProviderTranscriber::class));
        $this->send()->assertOk()->assertJsonPath('ai_available', false);
        $this->assertSame([], $this->fakeAi->calls);
    }

    public function test_external_provider_allowed_calls_fake_driver(): void
    {
        config(['operations.data_processors.speech_to_text.approved' => true, 'operations.data_processors.speech_to_text.allowed_data' => ['voice_recording']]);
        $this->app->instance(SpeechToText::class, app(ProviderTranscriber::class));
        $this->send()->assertOk()->assertJsonPath('draft.extractor', 'provider')->assertJsonPath('draft.suggested_amount', '5000');
        $this->assertCount(1, $this->fakeAi->calls);
        $this->assertArrayHasKey('transcribe', $this->fakeAi->calls[0]);
    }

    public function test_local_whisper_driver_builds_command_and_handles_missing_binary(): void
    {
        $w = new class extends WhisperCppTranscriber
        {
            public array $cmd = [];

            protected function makeProcess(array $command): Process
            {
                $this->cmd = $command;

                return new Process(['php', '-r', 'echo " قبضت 200 دولار من سامر ";']);
            }
        };
        $this->approveAiSharing(false); // local: no approval needed
        $this->assertSame('قبضت 200 دولار من سامر', $w->transcribe('/tmp/a.wav', 'audio/wav'));
        $this->assertSame(['-f', '/tmp/a.wav', '-l', 'ar'], array_slice($w->cmd, 3, 4));

        config(['ai.voice.whisper_path' => '/nonexistent/whisper-cli']);
        $this->assertNull((new WhisperCppTranscriber)->transcribe('/tmp/a.wav', 'audio/wav'));
        $this->app->forgetInstance(SpeechToText::class);
        $this->assertInstanceOf(WhisperCppTranscriber::class, $this->app->make(SpeechToText::class));
    }

    public function test_parser_fields_and_yesterday_without_type(): void
    {
        $p = (new VoiceDraftParser)->parse('قبضت مبارح ٢٠٠ دولار من سامر');
        $this->assertSame(['200', 'USD', now()->subDay()->toDateString(), 'سامر'], [$p['amount'], $p['currency'], $p['date'], $p['counterparty']]);
        $this->assertArrayNotHasKey('type', $p);
        $this->assertLessThanOrEqual(0.5, $p['confidence']);
    }

    public function test_type_comes_from_shared_suggester_and_categories_follow(): void
    {
        $this->stubStt('تم استلام دفعة من زبون 200 دولار');
        $this->send()->assertOk()
            ->assertJsonPath('draft.suggested_transaction_type', 'income')->assertJsonPath('type', 'income');

        // Spoken income word (shared suggester, #25).
        $this->stubStt('قبضت 200 دولار');
        $this->send()->assertOk()->assertJsonPath('draft.suggested_transaction_type', 'income')->assertJsonPath('type', 'income');

        // Categorizer receives the suggested type's list.
        $seen = null;
        $this->app->instance(TransactionCategorizer::class, new class($seen) implements TransactionCategorizer
        {
            public function __construct(public mixed &$seen) {}

            public function name(): string
            {
                return 'spy';
            }

            public function suggest(string $type, ?string $description, ?string $counterparty, array $allowedCategories): ?CategorySuggestion
            {
                $this->seen = [$type, $allowedCategories];

                return null;
            }
        });
        $this->stubStt('إيصال استلام 50');
        $this->send()->assertOk();
        $this->assertSame(['income', config('bookkeeping.categories.income')], $seen);
    }

    public function test_unavailable_transcript_has_null_suggested_type(): void
    {
        $this->stubStt(null);
        $this->send()->assertOk()->assertJsonPath('draft.suggested_transaction_type', null)->assertJsonPath('type', 'expense');
    }

    public function test_voice_rent_sentence_suggests_rent_category(): void
    {
        $this->stubStt('دفعت خمسين الف ليرة اجار محل اليوم');
        $this->send()->assertOk()->assertJsonPath('category_suggestion.category', 'rent')->assertJsonPath('type', 'expense');
    }

    public function test_voice_rent_variants_map_to_rent(): void
    {
        foreach (['دفعت أجار المحل', 'دفعت إيجار المحل', 'دفعت ايجار المحل'] as $t) {
            $this->stubStt($t);
            $this->send()->assertOk()->assertJsonPath('category_suggestion.category', 'rent');
        }
    }

    public function test_voice_food_sentence_suggests_meals(): void
    {
        $this->stubStt('دفعت عشرين الف ليرة بيتزا وعصير بالمطعم');
        $this->send()->assertOk()->assertJsonPath('category_suggestion.category', 'meals');
    }

    public function test_voice_unknown_sentence_has_no_category(): void
    {
        $this->stubStt('دفعت خمسين الف ليرة');
        $this->send()->assertOk()->assertJsonPath('category_suggestion', null);
    }

    public function test_voice_category_never_outside_config(): void
    {
        config(['bookkeeping.categories.expense' => ['other' => 'أخرى']]);
        $this->stubStt('دفعت خمسين الف ليرة اجار محل اليوم');
        $this->send()->assertOk()->assertJsonPath('category_suggestion', null);
    }

    // ---- transcript input (browser speech, no whisper) ----

    private function neverStt(): void
    {
        $this->app->instance(SpeechToText::class, new class implements SpeechToText
        {
            public function name(): string
            {
                return 'whisper';
            }

            public function transcribe(string $path, string $mimeType): ?string
            {
                throw new \RuntimeException('whisper must not be called for transcripts');
            }
        });
    }

    private function sendText(array $data, ?User $u = null, ?Enterprise $e = null)
    {
        return $this->actingAs($u ?? $this->sme)->post(route('ai.voice-drafts.store', $e ?? $this->enterprise), $data, ['Accept' => 'application/json']);
    }

    public function test_transcript_goes_to_parser_without_whisper(): void
    {
        $this->neverStt();
        $r = $this->sendText(['transcript' => '  دفعت ايجار محل 3000 الاف لمدة 3 اشهر كل شهر الف  ', 'source' => 'browser_speech'])->assertOk();
        $d = $r->json('draft');
        $this->assertSame('3000', $d['suggested_amount']);
        $this->assertCount(1, $d['suggested_items']);
        $this->assertEquals(3, $d['suggested_items'][0]['quantity']);
        $this->assertEquals(1000, $d['suggested_items'][0]['unit_price']);
        $this->assertArrayHasKey('items_mismatch', $d);
        $this->assertArrayHasKey('suggested_amount_warning', $d);
        $this->assertSame('browser_speech', $d['input_source']);
        $this->assertSame('voice', $d['source']);
        $this->assertSame('دفعت ايجار محل 3000 الاف لمدة 3 اشهر كل شهر الف', $d['raw_text']);
        $log = AuditLog::query()->where('action', 'ai.voice_draft.created')->latest('id')->first();
        $this->assertSame('browser_speech', $log->context['input_source']);
    }

    public function test_transcript_validation(): void
    {
        $this->neverStt();
        $this->sendText(['transcript' => 'دفعت الف', 'source' => 'browser_speech', 'audio' => $this->audio()])->assertStatus(422);
        $this->sendText([])->assertStatus(422)->assertJsonValidationErrors(['audio', 'transcript']);
        $this->sendText(['transcript' => str_repeat('ا', 1001), 'source' => 'browser_speech'])->assertStatus(422)->assertJsonValidationErrors('transcript');
        $this->sendText(['transcript' => '   ', 'source' => 'browser_speech'])->assertStatus(422);
        $this->sendText(['transcript' => 'دفعت الف'])->assertStatus(422)->assertJsonValidationErrors('source');
        $this->sendText(['transcript' => 'دفعت الف', 'source' => 'whisper'])->assertStatus(422)->assertJsonValidationErrors('source');
        $this->sendText(['transcript' => str_repeat('ا', 1000), 'source' => 'browser_speech'])->assertOk();
    }

    public function test_transcript_access_flag_and_rate_limit(): void
    {
        $this->neverStt();
        $ok = ['transcript' => 'دفعت الف', 'source' => 'browser_speech'];
        $other = Enterprise::query()->create(['business_name' => 'أخرى', 'location' => 'damascus']);
        $this->sendText($ok, null, $other)->assertForbidden();
        $this->sendText($ok, $this->userWithRole(UserRole::SmeUser))->assertForbidden();
        config(['ai.voice.enabled' => false]);
        $this->sendText($ok)->assertNotFound();
    }

    public function test_transcript_shares_voice_rate_limit(): void
    {
        $this->neverStt();
        $ok = ['transcript' => 'دفعت الف', 'source' => 'browser_speech'];
        for ($i = 0; $i < 10; $i++) {
            $this->sendText($ok)->assertOk();
        }
        $this->sendText($ok)->assertStatus(429);
    }
}
