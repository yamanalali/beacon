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
 * Low-cost OpenAI adapter (rules §71).
 *
 * Cost strategy (voice + receipt vision):
 * - Chat/vision: gpt-6-luna (cheapest high-volume OpenAI model with image input).
 * - reasoning_effort=none on Chat Completions so we do not pay for unused reasoning.
 * - Receipt images: resized JPEG + vision detail=low (fixed cheap image tokens).
 * - Speech: dedicated /audio/transcriptions with gpt-4o-mini-transcribe
 *   (Luna has no transcription endpoint; this remains the cheap STT path).
 * - Prefer browser Web Speech on the client when available (zero OpenAI cost).
 *
 * Business modules never depend on this class — only AiProvider.
 */
class OpenAiAiProvider implements AiProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.openai.com/v1',
        private readonly string $chatModel = 'gpt-6-luna',
        private readonly string $visionModel = 'gpt-6-luna',
        private readonly string $transcriptionModel = 'gpt-4o-mini-transcribe',
        private readonly int $timeout = 60,
        private readonly int $visionMaxEdge = 1280,
        private readonly int $visionJpegQuality = 72,
        private readonly string $visionDetail = 'low',
        private readonly int $maxTokensChat = 400,
        private readonly int $maxTokensVision = 220,
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
            // Cap article body size to reduce input tokens (§74 + cost).
            'body' => mb_substr((string) $k['body'], 0, 1200),
        ])->values()->all();

        $payload = $this->chatJson($this->chatModel, [
            [
                'role' => 'system',
                'content' => 'Answer ONLY from approved knowledge. Plain Arabic. JSON only: '
                    .'{"supported":boolean,"text":string,"source_ids":number[]}. '
                    .'If unsupported: supported=false,text="",source_ids=[]. '
                    .'Never invent policy. Never approve financing/compliance/permissions.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'question' => mb_substr($question, 0, 500),
                    'knowledge' => $articles,
                ], JSON_UNESCAPED_UNICODE),
            ],
        ], maxTokens: $this->maxTokensChat);

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

        [$dataUrl, $usedMime] = $this->cheapImageDataUrl($contents, $mimeType);

        // Line items need readable text — bump past "low" when the env left the cheap default.
        $detail = $this->visionDetail === 'low' ? 'auto' : $this->visionDetail;

        $payload = $this->chatJson($this->visionModel, [
            [
                'role' => 'system',
                'content' => 'Extract receipt/invoice fields. JSON only: '
                    .'{"transaction_date":"YYYY-MM-DD"|null,"amount":"decimal"|null,'
                    .'"currency":"ISO-4217"|null,"counterparty":string|null,'
                    .'"invoice_number":string|null,'
                    .'"items":[{"description":string|null,"quantity":number|null,"unit_price":number|null,"total":number|null}],'
                    .'"confidence":0..1}. '
                    .'PRIORITY: amount MUST be the printed FINAL total '
                    .'(المجموع النهائي / الإجمالي النهائي / Grand Total / Net Payable) — never a subtotal, tax-only line, or item sum invented by you. '
                    .'Western digits. Prefer SYP for Syrian pounds. '
                    .'items and tax/fee rows (ضريبة، رسم، اعادة اعمار) are OPTIONAL: include when clearly readable, else items=[]. '
                    .'Do not invent missing qty/price. Null when unclear.',
            ],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'Extract the FINAL total first, then optional line items from this receipt.'],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $dataUrl,
                            'detail' => $detail,
                        ],
                    ],
                ],
            ],
        ], maxTokens: max(700, $this->maxTokensVision));

        unset($usedMime);

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
                'content' => 'Pick one allowed category key. JSON only: '
                    .'{"category":string|null,"confidence":0..1}. Null if none fits.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'type' => $type,
                    'description' => $description !== null ? mb_substr($description, 0, 240) : null,
                    'counterparty' => $counterparty !== null ? mb_substr($counterparty, 0, 120) : null,
                    'allowed_categories' => $allowedCategories,
                ], JSON_UNESCAPED_UNICODE),
            ],
        ], maxTokens: 80);

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
                'content' => 'Explain supplied financial figures in plain Arabic. '
                    .'Do not recalculate or invent numbers. Under 400 words. Plain text only.',
            ],
            [
                'role' => 'user',
                'content' => json_encode($figures, JSON_UNESCAPED_UNICODE),
            ],
        ], maxTokens: min(500, $this->maxTokensChat));

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

        try {
            $response = $this->http()
                ->attach('file', $audio, 'voice.'.$format)
                ->post(rtrim($this->baseUrl, '/').'/audio/transcriptions', [
                    'model' => $this->transcriptionModel,
                    'language' => 'ar',
                    // Keep output short; voice drafts are brief SME notes.
                    'response_format' => 'json',
                ]);
        } catch (ConnectionException $e) {
            throw new AiUnavailableException('OpenAI transcription unreachable: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw $this->unavailableFromHttp($response->status(), (string) $response->body());
        }

        $text = trim((string) ($response->json('text') ?? ''));
        if ($text === '') {
            throw new AiUnavailableException('OpenAI returned an empty transcript.');
        }

        return mb_substr($text, 0, 2000);
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
        ], maxTokens: min(320, $this->maxTokensChat));

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

    /**
     * Shrink + JPEG-compress receipt images before vision upload.
     *
     * @return array{0:string,1:string} [dataUrl, mime]
     */
    private function cheapImageDataUrl(string $contents, string $mimeType): array
    {
        if (! extension_loaded('gd')) {
            return ['data:'.$mimeType.';base64,'.base64_encode($contents), $mimeType];
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return ['data:'.$mimeType.';base64,'.base64_encode($contents), $mimeType];
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $max = max(1, $this->visionMaxEdge);

        if ($width > $max || $height > $max) {
            $scale = min($max / $width, $max / $height);
            $newW = max(1, (int) round($width * $scale));
            $newH = max(1, (int) round($height * $scale));
            $resized = imagecreatetruecolor($newW, $newH);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }

        ob_start();
        imagejpeg($image, null, max(40, min(90, $this->visionJpegQuality)));
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        if ($jpeg === '' || strlen($jpeg) >= strlen($contents)) {
            // Keep original if JPEG somehow grew (rare for already-tiny files).
            return ['data:'.$mimeType.';base64,'.base64_encode($contents), $mimeType];
        }

        return ['data:image/jpeg;base64,'.base64_encode($jpeg), 'image/jpeg'];
    }

    /**
     * @param  list<array<string,mixed>>  $messages
     * @return array<string,mixed>
     */
    private function chatJson(string $model, array $messages, int $maxTokens = 300): array
    {
        $content = $this->chatText($model, $messages, json: true, maxTokens: $maxTokens);
        $decoded = json_decode($this->extractJsonObject($content), true);
        if (! is_array($decoded)) {
            throw new AiUnavailableException('OpenAI returned invalid JSON.');
        }

        return $decoded;
    }

    /**
     * @param  list<array<string,mixed>>  $messages
     */
    private function chatText(string $model, array $messages, bool $json = false, int $maxTokens = 400): string
    {
        $this->assertConfigured();

        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0,
            // gpt-6-luna (and newer chat models) reject max_tokens — use max_completion_tokens.
            'max_completion_tokens' => max(32, $maxTokens),
            // Luna defaults to medium reasoning; disable for cheap extract/JSON tasks.
            'reasoning_effort' => 'none',
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
            throw new AiUnavailableException('OpenAI unreachable: '.$e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            throw $this->unavailableFromResponse($e);
        } catch (Throwable $e) {
            throw new AiUnavailableException('OpenAI request failed: '.$e->getMessage(), 0, $e);
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new AiUnavailableException('OpenAI returned an empty response.');
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
        $request = Http::withToken($this->apiKey)->timeout($this->timeout);

        $verify = $this->verifySsl;
        if (is_string($verify) && $verify !== '' && is_file($verify)) {
            $request = $request->withOptions(['verify' => $verify]);
        } elseif ($verify === false) {
            $request = $request->withOptions(['verify' => false]);
        }

        return $request;
    }

    private function unavailableFromResponse(RequestException $e): AiUnavailableException
    {
        return $this->unavailableFromHttp($e->response->status(), (string) $e->response->body(), $e);
    }

    private function unavailableFromHttp(int $status, string $body, ?Throwable $previous = null): AiUnavailableException
    {
        $detail = mb_substr($body, 0, 300);

        return new AiUnavailableException('OpenAI request failed (HTTP '.$status.'): '.$detail, $status, $previous);
    }

    private function assertConfigured(): void
    {
        if ($this->apiKey === '') {
            throw new AiUnavailableException('OpenAI API key is not configured.');
        }
    }
}
