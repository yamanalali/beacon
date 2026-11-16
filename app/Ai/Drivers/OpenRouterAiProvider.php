<?php

namespace App\Ai\Drivers;

use App\Ai\Contracts\AiProvider;
use App\Ai\Data\AiAnswer;
use App\Ai\Exceptions\AiUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * OpenRouter adapter (OpenAI-compatible HTTP API). Business modules depend only
 * on AiProvider; this class is the replaceable vendor adapter (rules §71).
 *
 * Secrets stay in env. Failures become AiUnavailableException so non-AI
 * workflows keep working (rules §77).
 */
class OpenRouterAiProvider implements AiProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $chatModel,
        private readonly string $visionModel,
        private readonly string $transcriptionModel,
        private readonly string $transcriptionMode = 'chat',
        private readonly int $timeout = 90,
        private readonly ?string $httpReferer = null,
        private readonly ?string $appTitle = null,
        private readonly mixed $verifySsl = true,
    ) {}

    public function answerFromKnowledge(string $question, array $knowledge): AiAnswer
    {
        if ($knowledge === []) {
            return new AiAnswer(false, '');
        }

        $articles = collect($knowledge)->map(fn ($k) => [
            'id' => (int) $k['id'],
            'title' => (string) $k['title'],
            'body' => (string) $k['body'],
        ])->values()->all();

        $payload = $this->chatJson($this->chatModel, [
            [
                'role' => 'system',
                'content' => 'You are Beacon\'s advisory assistant. Answer ONLY from the approved knowledge articles. '
                    .'Reply in plain Arabic. Return JSON only: '
                    .'{"supported":boolean,"text":string,"source_ids":number[]}. '
                    .'If the articles do not support an answer, set supported=false, text="", source_ids=[]. '
                    .'Never invent Beacon policy. Never approve financing, compliance, or permissions.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'question' => $question,
                    'knowledge' => $articles,
                ], JSON_UNESCAPED_UNICODE),
            ],
        ]);

        $supported = (bool) ($payload['supported'] ?? false);
        $text = trim((string) ($payload['text'] ?? ''));
        $sourceIds = [];
        foreach ($payload['source_ids'] ?? [] as $id) {
            if (is_numeric($id)) {
                $sourceIds[] = (int) $id;
            }
        }
        $allowed = array_column($articles, 'id');
        $sourceIds = array_values(array_intersect($sourceIds, $allowed));

        if (! $supported || $text === '') {
            return new AiAnswer(false, '');
        }

        return new AiAnswer(true, mb_substr($text, 0, 4000), $sourceIds);
    }

    public function extractReceipt(string $contents, string $mimeType): array
    {
        if (! str_starts_with($mimeType, 'image/')) {
            // Vision path is image-only; PDF/other stay review-failed upstream.
            return [
                'transaction_date' => null,
                'amount' => null,
                'currency' => null,
                'counterparty' => null,
                'confidence' => null,
                'invoice_number' => null,
                'items' => [],
            ];
        }

        $dataUrl = 'data:'.$mimeType.';base64,'.base64_encode($contents);

        $payload = $this->chatJson($this->visionModel, [
            [
                'role' => 'system',
                'content' => 'Extract receipt/invoice fields. Return JSON only: '
                    .'{"transaction_date":"YYYY-MM-DD"|null,"amount":"decimal string"|null,'
                    .'"currency":"ISO-4217"|null,"counterparty":string|null,'
                    .'"invoice_number":string|null,'
                    .'"items":[{"description":string|null,"quantity":number|null,"unit_price":number|null,"total":number|null}],'
                    .'"confidence":0..1}. '
                    .'PRIORITY: amount MUST be the printed FINAL total '
                    .'(المجموع النهائي / الإجمالي النهائي / Grand Total) — never a subtotal or tax-only line. '
                    .'Use Western digits. Prefer SYP when clearly Syrian pounds. '
                    .'items and tax/fee rows are OPTIONAL; empty items=[] when unclear. '
                    .'Do not invent values; use null when unclear.',
            ],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Extract the FINAL total first, then optional line items from this receipt image.'],
                    ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                ],
            ],
        ]);

        return [
            'transaction_date' => isset($payload['transaction_date']) ? (string) $payload['transaction_date'] : null,
            'amount' => isset($payload['amount']) ? (string) $payload['amount'] : null,
            'currency' => isset($payload['currency']) ? (string) $payload['currency'] : null,
            'counterparty' => isset($payload['counterparty']) ? (string) $payload['counterparty'] : null,
            'confidence' => isset($payload['confidence']) ? (float) $payload['confidence'] : null,
            'invoice_number' => isset($payload['invoice_number']) ? (string) $payload['invoice_number'] : null,
            'items' => is_array($payload['items'] ?? null) ? $payload['items'] : [],
        ];
    }

    public function suggestCategory(string $type, ?string $description, ?string $counterparty, array $allowedCategories): array
    {
        if ($allowedCategories === []) {
            return ['category' => null, 'confidence' => null];
        }

        $payload = $this->chatJson($this->chatModel, [
            [
                'role' => 'system',
                'content' => 'Choose exactly one category key from the allowed list. Return JSON only: '
                    .'{"category":string|null,"confidence":0..1}. '
                    .'If none fits, category=null. Never invent a key outside the list.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'type' => $type,
                    'description' => $description,
                    'counterparty' => $counterparty,
                    'allowed_categories' => $allowedCategories,
                ], JSON_UNESCAPED_UNICODE),
            ],
        ]);

        $category = $payload['category'] ?? null;
        if (! is_string($category) || ! array_key_exists($category, $allowedCategories)) {
            $category = null;
        }

        return [
            'category' => $category,
            'confidence' => isset($payload['confidence']) ? (float) $payload['confidence'] : null,
        ];
    }

    public function explainFigures(array $figures): string
    {
        $text = $this->chatText($this->chatModel, [
            [
                'role' => 'system',
                'content' => 'Explain the supplied financial figures in plain Arabic for an SME owner. '
                    .'Do not recalculate totals. Do not invent numbers. '
                    .'If limitations are listed, mention them briefly. Keep under 600 words. Plain text only.',
            ],
            [
                'role' => 'user',
                'content' => json_encode($figures, JSON_UNESCAPED_UNICODE),
            ],
        ]);

        return mb_substr(trim($text), 0, 4000);
    }

    public function transcribe(string $audio, string $mimeType): string
    {
        $this->assertConfigured();

        $format = match (true) {
            str_contains($mimeType, 'webm') => 'webm',
            str_contains($mimeType, 'ogg') => 'ogg',
            str_contains($mimeType, 'mpeg'), str_contains($mimeType, 'mp3') => 'mp3',
            str_contains($mimeType, 'wav') => 'wav',
            str_contains($mimeType, 'mp4'), str_contains($mimeType, 'm4a') => 'm4a',
            default => 'webm',
        };

        // Free path: multimodal chat with input_audio (Whisper /audio/transcriptions is paid on OpenRouter).
        if ($this->transcriptionMode !== 'audio_api') {
            return $this->transcribeViaChat($audio, $format);
        }

        return $this->transcribeViaAudioApi($audio, $format);
    }

    public function structureVoiceDraft(string $transcript): array
    {
        $today = now()->toDateString();
        $typeKeys = ['income', 'expense', 'sale', 'purchase'];
        $categoryMap = config('bookkeeping.categories', []);
        $flatCategories = [];
        foreach ($categoryMap as $type => $cats) {
            if (! is_array($cats)) {
                continue;
            }
            foreach ($cats as $key => $label) {
                $flatCategories[] = ['type' => (string) $type, 'key' => (string) $key, 'label' => (string) $label];
            }
        }

        $payload = $this->chatJson($this->chatModel, [
            [
                'role' => 'system',
                'content' => 'Structure one Arabic SME voice note into a draft transaction. JSON only: '
                    .'{"amount":"decimal"|null,"date":"YYYY-MM-DD"|null,"currency":"ISO-4217"|null,'
                    .'"counterparty":string|null,"note":string|null,'
                    .'"transaction_type":"income"|"expense"|"sale"|"purchase"|null,'
                    .'"category":string|null,'
                    .'"items":[{"description":string,"quantity":number,"unit_price":number,"total":number}],'
                    .'"items_mismatch":boolean,"amount_warning":string|null,"confidence":0..1}. '
                    .'Western digits. Prefer SYP for Syrian pounds/ليرة. '
                    .'Relative dates use today='.$today.' (اليوم/مبارح). '
                    .'Periods like لمدة 3 أشهر go to note only — never multiply amount. '
                    .'category must be a key from allowed list for that type, else null. '
                    .'items empty when not clearly listed. No invention. Suggestions only.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'transcript' => mb_substr($transcript, 0, 1000),
                    'allowed_transaction_types' => $typeKeys,
                    'allowed_categories' => array_slice($flatCategories, 0, 80),
                ], JSON_UNESCAPED_UNICODE),
            ],
        ]);

        $items = [];
        foreach ($payload['items'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $items[] = [
                'description' => (string) ($row['description'] ?? ''),
                'quantity' => $row['quantity'] ?? 0,
                'unit_price' => $row['unit_price'] ?? 0,
                'total' => $row['total'] ?? 0,
            ];
        }

        return [
            'amount' => isset($payload['amount']) ? (string) $payload['amount'] : null,
            'date' => isset($payload['date']) ? (string) $payload['date'] : null,
            'currency' => isset($payload['currency']) ? (string) $payload['currency'] : null,
            'counterparty' => isset($payload['counterparty']) ? (string) $payload['counterparty'] : null,
            'note' => isset($payload['note']) ? (string) $payload['note'] : null,
            'transaction_type' => isset($payload['transaction_type']) ? (string) $payload['transaction_type'] : null,
            'category' => isset($payload['category']) ? (string) $payload['category'] : null,
            'items' => $items,
            'items_mismatch' => (bool) ($payload['items_mismatch'] ?? false),
            'amount_warning' => isset($payload['amount_warning']) ? (string) $payload['amount_warning'] : null,
            'confidence' => isset($payload['confidence']) ? (float) $payload['confidence'] : null,
        ];
    }

    private function transcribeViaChat(string $audio, string $format): string
    {
        $text = $this->chatText($this->transcriptionModel, [
            [
                'role' => 'system',
                'content' => 'You are a speech-to-text engine for Syrian/Levantine Arabic business voice notes. '
                    .'Transcribe the audio verbatim into Arabic text only. '
                    .'Keep spoken numbers as spoken words or digits exactly as heard. '
                    .'Do not translate, summarize, or add commentary. '
                    .'If the audio is empty or unintelligible, reply with an empty string.',
            ],
            [
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Transcribe this Arabic voice note for a bookkeeping draft. Output only the transcript.',
                    ],
                    [
                        'type' => 'input_audio',
                        'input_audio' => [
                            'data' => base64_encode($audio),
                            'format' => $format,
                        ],
                    ],
                ],
            ],
        ]);

        $text = trim($text);
        if ($text === '') {
            throw new AiUnavailableException('OpenRouter returned an empty transcript.');
        }

        return mb_substr($text, 0, 2000);
    }

    private function transcribeViaAudioApi(string $audio, string $format): string
    {
        try {
            $response = $this->http()
                ->attach('file', $audio, 'voice.'.$format)
                ->post(rtrim($this->baseUrl, '/').'/audio/transcriptions', [
                    'model' => $this->transcriptionModel,
                    'language' => 'ar',
                ]);
        } catch (ConnectionException $e) {
            throw new AiUnavailableException('OpenRouter transcription unreachable: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw $this->unavailableFromHttp($response->status(), (string) $response->body());
        }

        $text = trim((string) ($response->json('text') ?? ''));
        if ($text === '') {
            throw new AiUnavailableException('OpenRouter returned an empty transcript.');
        }

        return mb_substr($text, 0, 2000);
    }

    /**
     * @param  list<array<string,mixed>>  $messages
     * @return array<string,mixed>
     */
    private function chatJson(string $model, array $messages): array
    {
        $content = $this->chatText($model, $messages, json: true);
        $decoded = json_decode($this->extractJsonObject($content), true);
        if (! is_array($decoded)) {
            throw new AiUnavailableException('OpenRouter returned invalid JSON.');
        }

        return $decoded;
    }

    /**
     * @param  list<array<string,mixed>>  $messages
     */
    private function chatText(string $model, array $messages, bool $json = false): string
    {
        $this->assertConfigured();

        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.2,
        ];
        if ($json) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = $this->http()
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', $body)
                ->throw();
        } catch (ConnectionException $e) {
            throw new AiUnavailableException('OpenRouter unreachable: '.$e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            throw $this->unavailableFromResponse($e);
        } catch (Throwable $e) {
            throw new AiUnavailableException('OpenRouter request failed: '.$e->getMessage(), 0, $e);
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new AiUnavailableException('OpenRouter returned an empty response.');
        }

        return $content;
    }

    private function extractJsonObject(string $content): string
    {
        $trimmed = trim($content);
        if (str_starts_with($trimmed, '{')) {
            return $trimmed;
        }

        if (preg_match('/\{.*\}/s', $trimmed, $m) === 1) {
            return $m[0];
        }

        return $trimmed;
    }

    private function http(): PendingRequest
    {
        $request = Http::withToken($this->apiKey)
            ->withHeaders($this->attributionHeaders())
            ->timeout($this->timeout);

        $verify = $this->verifySsl;
        if (is_string($verify) && $verify !== '' && is_file($verify)) {
            $request = $request->withOptions(['verify' => $verify]);
        } elseif ($verify === false) {
            $request = $request->withOptions(['verify' => false]);
        }

        return $request;
    }

    /** @return array<string,string> */
    private function attributionHeaders(): array
    {
        $headers = [];
        if ($this->httpReferer) {
            $headers['HTTP-Referer'] = $this->httpReferer;
        }
        if ($this->appTitle) {
            $headers['X-Title'] = $this->appTitle;
        }

        return $headers;
    }

    private function unavailableFromResponse(RequestException $e): AiUnavailableException
    {
        return $this->unavailableFromHttp($e->response->status(), (string) $e->response->body(), $e);
    }

    private function unavailableFromHttp(int $status, string $body, ?Throwable $previous = null): AiUnavailableException
    {
        $detail = mb_substr($body, 0, 300);
        if ($status === 402 || str_contains($body, 'requires at least $0.50 in balance for audio')) {
            return new AiUnavailableException('OpenRouter audio requires account credits (HTTP 402).', 402, $previous);
        }

        return new AiUnavailableException('OpenRouter request failed (HTTP '.$status.'): '.$detail, $status, $previous);
    }

    private function assertConfigured(): void
    {
        if ($this->apiKey === '') {
            throw new AiUnavailableException('OpenRouter API key is not configured.');
        }
    }
}
