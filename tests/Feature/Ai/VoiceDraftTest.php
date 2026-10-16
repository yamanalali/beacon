<?php

namespace Tests\Feature\Ai;

use App\Ai\Voice\ProviderTranscriber;
use App\Ai\Voice\SpeechToText;
use App\Ai\Voice\VoiceDraftParser;
use App\Ai\Voice\WhisperCppTranscriber;
use App\Contracts\TransactionCategorizer;
use App\Enums\UserRole;
use App\Models\Enterprise;
use App\Models\User;
use App\Services\Ai\CategorySuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    public function test_disabled_by_default_returns_404(): void
    {
        config(['ai.voice.enabled' => false]);
        $this->stubStt('دفعت 100');
        $this->send()->assertNotFound();
        $this->assertFalse((bool) (require base_path('config/ai.php'))['voice']['enabled']);
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
}
