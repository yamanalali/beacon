<?php

namespace Tests\Unit\Ai;

use App\Ai\Drivers\OpenAiAiProvider;
use App\Ai\Exceptions\AiUnavailableException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiAiProviderTest extends TestCase
{
    private function provider(array $overrides = []): OpenAiAiProvider
    {
        return new OpenAiAiProvider(
            apiKey: $overrides['api_key'] ?? 'test-key',
            baseUrl: $overrides['base_url'] ?? 'https://api.openai.com/v1',
            chatModel: $overrides['chat_model'] ?? 'gpt-6-luna',
            visionModel: $overrides['vision_model'] ?? 'gpt-6-luna',
            transcriptionModel: $overrides['transcription_model'] ?? 'gpt-4o-mini-transcribe',
            timeout: $overrides['timeout'] ?? 10,
            visionMaxEdge: $overrides['vision_max_edge'] ?? 1280,
            visionJpegQuality: $overrides['vision_jpeg_quality'] ?? 72,
            visionDetail: $overrides['vision_detail'] ?? 'low',
            maxTokensChat: $overrides['max_tokens_chat'] ?? 400,
            maxTokensVision: $overrides['max_tokens_vision'] ?? 220,
            verifySsl: $overrides['verify_ssl'] ?? true,
        );
    }

    public function test_missing_api_key_is_unavailable(): void
    {
        $this->expectException(AiUnavailableException::class);
        $this->provider(['api_key' => ''])->explainFigures(['period' => '2026-09']);
    }

    public function test_transcribe_uses_audio_transcriptions_endpoint(): void
    {
        Http::fake([
            'https://api.openai.com/v1/audio/transcriptions' => Http::response([
                'text' => 'دفعت خمسة آلاف ليرة أجار',
            ]),
        ]);

        $text = $this->provider()->transcribe('fake-audio-bytes', 'audio/webm');
        $this->assertSame('دفعت خمسة آلاف ليرة أجار', $text);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/audio/transcriptions'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/chat/completions'));
    }

    public function test_extract_receipt_sends_low_detail_and_caps_tokens(): void
    {
        // 1x1 PNG
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'transaction_date' => '2026-10-01',
                            'amount' => '250.00',
                            'currency' => 'SYP',
                            'counterparty' => 'محل',
                            'invoice_number' => '4521',
                            'items' => [
                                ['description' => 'قماش', 'quantity' => 2, 'unit_price' => 100, 'total' => 200],
                                ['description' => 'خيط', 'quantity' => 1, 'unit_price' => 50, 'total' => 50],
                            ],
                            'confidence' => 0.8,
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ]),
        ]);

        $result = $this->provider()->extractReceipt($png, 'image/png');
        $this->assertSame('2026-10-01', $result['transaction_date']);
        $this->assertSame('250.00', $result['amount']);
        $this->assertSame('4521', $result['invoice_number']);
        $this->assertCount(2, $result['items']);

        Http::assertSent(function ($request) {
            $image = $request['messages'][1]['content'][1]['image_url'] ?? [];
            $system = (string) ($request['messages'][0]['content'] ?? '');

            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && ($request['model'] ?? null) === 'gpt-6-luna'
                && ($request['reasoning_effort'] ?? null) === 'none'
                && ($request['max_completion_tokens'] ?? null) === 700
                && ($image['detail'] ?? null) === 'auto'
                && str_contains($system, 'items')
                && is_string($image['url'] ?? null)
                && str_starts_with($image['url'], 'data:image/');
        });
    }

    public function test_suggest_category_uses_tight_max_tokens(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode(['category' => 'rent', 'confidence' => 0.9]),
                    ],
                ]],
            ]),
        ]);

        $result = $this->provider()->suggestCategory('expense', 'إيجار المحل', null, [
            'rent' => 'إيجار',
            'utilities' => 'مرافق',
        ]);

        $this->assertSame('rent', $result['category']);

        Http::assertSent(fn ($request) => ($request['max_completion_tokens'] ?? null) === 80);
    }

    public function test_structure_voice_draft_uses_chat_not_audio(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'amount' => '50000',
                            'date' => '2026-11-13',
                            'currency' => 'SYP',
                            'counterparty' => null,
                            'note' => 'لمدة ثلاثة اشهر',
                            'transaction_type' => 'expense',
                            'category' => 'rent',
                            'items' => [],
                            'items_mismatch' => false,
                            'amount_warning' => null,
                            'confidence' => 0.85,
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ]),
        ]);

        $result = $this->provider()->structureVoiceDraft('دفعت اجار محل 50 الف ليرة لمدة ثلاثة اشهر');
        $this->assertSame('50000', $result['amount']);
        $this->assertSame('expense', $result['transaction_type']);
        $this->assertSame('rent', $result['category']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/chat/completions')
            && ($request['model'] ?? null) === 'gpt-6-luna'
            && ($request['reasoning_effort'] ?? null) === 'none');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/audio/transcriptions'));
    }
}
