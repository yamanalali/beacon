<?php

namespace Tests\Unit\Ai;

use App\Ai\Drivers\OpenRouterAiProvider;
use App\Ai\Exceptions\AiUnavailableException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterAiProviderTest extends TestCase
{
    private function provider(array $overrides = []): OpenRouterAiProvider
    {
        return new OpenRouterAiProvider(
            apiKey: $overrides['api_key'] ?? 'test-key',
            baseUrl: $overrides['base_url'] ?? 'https://openrouter.ai/api/v1',
            chatModel: $overrides['chat_model'] ?? 'nvidia/nemotron-3.5-lightning:free',
            visionModel: $overrides['vision_model'] ?? 'google/gemma-4-26b-a4b-it:free',
            transcriptionModel: $overrides['transcription_model'] ?? 'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free',
            transcriptionMode: $overrides['transcription_mode'] ?? 'chat',
            timeout: $overrides['timeout'] ?? 10,
            httpReferer: $overrides['http_referer'] ?? 'https://beacon.test',
            appTitle: $overrides['app_title'] ?? 'Beacon',
            verifySsl: $overrides['verify_ssl'] ?? true,
        );
    }

    public function test_missing_api_key_is_unavailable(): void
    {
        $this->expectException(AiUnavailableException::class);
        $this->provider(['api_key' => ''])->explainFigures(['period' => '2026-09']);
    }

    public function test_answer_from_knowledge_parses_json_and_filters_source_ids(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'supported' => true,
                            'text' => 'سجّل المصروف مع الإيصال.',
                            'source_ids' => [1, 99],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ]),
        ]);

        $answer = $this->provider()->answerFromKnowledge('كيف أسجل المصاريف؟', [
            ['id' => 1, 'title' => 'مصاريف', 'body' => 'سجّل كل مصروف مع الإيصال.'],
        ]);

        $this->assertTrue($answer->supported);
        $this->assertSame('سجّل المصروف مع الإيصال.', $answer->text);
        $this->assertSame([1], $answer->sourceIds);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && $request->hasHeader('HTTP-Referer', 'https://beacon.test')
                && $request['model'] === 'nvidia/nemotron-3.5-lightning:free'
                && ($request['response_format']['type'] ?? null) === 'json_object';
        });
    }

    public function test_transcribe_uses_free_chat_audio_by_default(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'دفعت خمسة آلاف ليرة أجار']]],
            ]),
        ]);

        $text = $this->provider()->transcribe('fake-audio-bytes', 'audio/webm');
        $this->assertSame('دفعت خمسة آلاف ليرة أجار', $text);

        Http::assertSent(function ($request) {
            $content = $request['messages'][1]['content'] ?? null;

            return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
                && $request['model'] === 'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free'
                && is_array($content)
                && ($content[1]['type'] ?? null) === 'input_audio'
                && ($content[1]['input_audio']['format'] ?? null) === 'webm';
        });
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/audio/transcriptions'));
    }

    public function test_transcribe_audio_api_mode_uses_transcriptions_endpoint(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/audio/transcriptions' => Http::response(['text' => 'مصروف مواصلات']),
        ]);

        $text = $this->provider([
            'transcription_mode' => 'audio_api',
            'transcription_model' => 'openai/whisper-large-v3-turbo',
        ])->transcribe('fake-audio-bytes', 'audio/wav');

        $this->assertSame('مصروف مواصلات', $text);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/audio/transcriptions'));
    }

    public function test_empty_knowledge_skips_network(): void
    {
        Http::fake();
        $answer = $this->provider()->answerFromKnowledge('سؤال', []);
        $this->assertFalse($answer->supported);
        Http::assertNothingSent();
    }

    public function test_suggest_category_discards_unknown_keys(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"category":"invented","confidence":0.9}']]],
            ]),
        ]);

        $r = $this->provider()->suggestCategory('expense', 'إيجار', null, ['rent' => 'إيجار']);
        $this->assertNull($r['category']);
    }

    public function test_suggest_category_accepts_allowed_key(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => '{"category":"rent","confidence":0.8}']]],
            ]),
        ]);

        $r = $this->provider()->suggestCategory('expense', 'إيجار المحل', null, ['rent' => 'إيجار', 'utilities' => 'مرافق']);
        $this->assertSame('rent', $r['category']);
        $this->assertSame(0.8, $r['confidence']);
    }

    public function test_extract_receipt_uses_vision_model_for_images(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'transaction_date' => '2026-10-01',
                            'amount' => '125.50',
                            'currency' => 'SYP',
                            'counterparty' => 'مورد',
                            'confidence' => 0.7,
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ]),
        ]);

        $r = $this->provider(['vision_model' => 'google/gemma-4-26b-a4b-it:free'])->extractReceipt('fake-image-bytes', 'image/jpeg');
        $this->assertSame('2026-10-01', $r['transaction_date']);
        $this->assertSame('125.50', $r['amount']);
        $this->assertSame('SYP', $r['currency']);
        $this->assertSame('مورد', $r['counterparty']);

        Http::assertSent(fn ($request) => $request['model'] === 'google/gemma-4-26b-a4b-it:free');
    }

    public function test_extract_receipt_skips_non_images(): void
    {
        Http::fake();
        $r = $this->provider()->extractReceipt('%PDF', 'application/pdf');
        $this->assertNull($r['amount']);
        Http::assertNothingSent();
    }

    public function test_explain_figures_returns_plain_text(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'المصروفات أعلى من الدخل هذا الشهر.']]],
            ]),
        ]);

        $text = $this->provider()->explainFigures(['period' => 'أيلول 2026', 'sections' => []]);
        $this->assertSame('المصروفات أعلى من الدخل هذا الشهر.', $text);
    }

    public function test_http_error_becomes_unavailable(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response(['error' => 'nope'], 502),
        ]);

        $this->expectException(AiUnavailableException::class);
        $this->provider()->explainFigures(['period' => 'x']);
    }

    public function test_service_provider_binds_openrouter_driver(): void
    {
        config([
            'ai.driver' => 'openrouter',
            'ai.openrouter.api_key' => 'from-config',
            'ai.openrouter.base_url' => 'https://openrouter.ai/api/v1',
            'ai.openrouter.chat_model' => 'nvidia/nemotron-3.5-lightning:free',
            'ai.openrouter.vision_model' => 'google/gemma-4-26b-a4b-it:free',
            'ai.openrouter.transcription_model' => 'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free',
            'ai.openrouter.transcription_mode' => 'chat',
        ]);
        $this->app->forgetInstance(\App\Ai\Contracts\AiProvider::class);
        $this->assertInstanceOf(OpenRouterAiProvider::class, $this->app->make(\App\Ai\Contracts\AiProvider::class));
    }
}
