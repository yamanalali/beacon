<?php

namespace App\Ai\Http\Controllers;

use App\Ai\Voice\SpeechToText;
use App\Ai\Voice\VoiceDraftStructurer;
use App\Contracts\TransactionCategorizer;
use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Finance\ReceiptTextCategorizer;
use App\Finance\TransactionTypeSuggester;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Services\Ai\CategorySuggestion;
use App\Support\Currencies;
use App\Support\SuggestedDateWarning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Voice draft transaction:
 * Preferred: browser Web Speech transcript -> ChatGPT structures fields -> user reviews.
 * Legacy: uploaded audio -> local/provider STT -> same structuring path.
 * Nothing is saved until the user submits enterprises.transactions.store.
 */
class VoiceDraftController extends Controller
{
    public const MIMES = ['audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/wav', 'audio/x-wav', 'audio/wave', 'video/webm'];

    public const TRANSCRIPT_MAX = 1000;

    public const TRANSCRIPT_SOURCES = ['browser_speech'];

    public function store(
        Request $request,
        Enterprise $enterprise,
        SpeechToText $stt,
        VoiceDraftStructurer $structurer,
        TransactionCategorizer $categorizer,
        TransactionTypeSuggester $typeSuggester,
        ReceiptTextCategorizer $textCategorizer,
    ): JsonResponse {
        abort_unless(config('ai.voice.enabled'), 404);
        abort_unless($request->user()->isActive(), 403);
        Gate::authorize('create', [FinancialTransaction::class, $enterprise]);

        if (is_string($request->input('transcript'))) {
            $request->merge(['transcript' => trim($request->input('transcript'))]);
        }
        $request->validate([
            'audio' => ['required_without:transcript', 'prohibits:transcript', 'file', 'mimetypes:'.implode(',', self::MIMES), 'max:'.(int) config('ai.voice.max_kb', 5120)],
            'transcript' => ['required_without:audio', 'prohibits:audio', 'string', 'max:'.self::TRANSCRIPT_MAX],
            'source' => ['required_with:transcript', 'prohibits:audio', 'string', 'in:'.implode(',', self::TRANSCRIPT_SOURCES)],
        ]);

        $fromTranscript = $request->has('transcript');
        $inputSource = $fromTranscript ? (string) $request->input('source') : 'audio';
        if ($fromTranscript) {
            // Browser speech: text only — no recording, no STT API cost.
            $text = (string) $request->input('transcript');
        } else {
            $file = $request->file('audio');
            $path = $file->getRealPath();
            $mime = (string) $file->getMimeType();
            try {
                $text = $stt->transcribe($path, $mime);
            } finally {
                if ($path && is_file($path)) {
                    @unlink($path);
                }
            }
        }
        $extractor = $fromTranscript ? $inputSource : $stt->name();

        $p = $text !== null
            ? $structurer->structure($text)
            : [
                'amount' => null, 'date' => null, 'counterparty' => null, 'currency' => null,
                'note' => null, 'items' => [], 'items_mismatch' => false, 'amount_warning' => null,
                'confidence' => null, 'normalized' => null, 'transaction_type' => null, 'category' => null,
                'structure_source' => 'none',
            ];

        $catText = $text !== null ? ($p['normalized'] ?? $text) : null;
        $suggestedType = $p['transaction_type'] ?? $typeSuggester->suggest($catText);
        $type = $suggestedType ?? 'expense';
        $allowed = config('bookkeeping.categories.'.$type, []);

        $suggestion = null;
        if ($text !== null && isset($p['category']) && is_string($p['category']) && array_key_exists($p['category'], $allowed)) {
            $suggestion = new CategorySuggestion($p['category'], $p['confidence'] ?? null, true);
        } elseif ($text !== null) {
            $suggestion = $categorizer->suggest($type, $catText, $p['counterparty'], $allowed);
            if ($suggestion === null) {
                $suggestion = $textCategorizer->suggest($catText, $allowed);
            }
        }
        if ($suggestion !== null && ! array_key_exists($suggestion->category, $allowed)) {
            $suggestion = null;
        }

        $currencies = array_keys(app(Currencies::class)->enabled());
        $spoken = in_array($p['currency'], $currencies, true) ? $p['currency'] : null;
        $base = app(Currencies::class)->default() ?: 'SYP';
        $currency = $spoken ?? ($text !== null && in_array($base, $currencies, true) ? $base : null);
        $currencySource = $spoken !== null ? 'spoken' : ($currency !== null ? 'default' : null);
        $typeOptions = collect(TransactionType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->all();

        AuditLog::query()->create([
            'actor_id' => $request->user()->id, 'action' => 'ai.voice_draft.created',
            'target_type' => Enterprise::class, 'target_id' => $enterprise->id,
            'context' => [
                'stt' => $extractor,
                'input_source' => $inputSource,
                'structure_source' => $p['structure_source'] ?? 'local',
                'transcribed' => $text !== null,
            ],
        ]);

        return response()->json([
            'enterprise' => ['public_id' => $enterprise->public_id, 'business_name' => $enterprise->business_name],
            'draft' => [
                'source' => 'voice',
                'extractor' => $extractor,
                'input_source' => $inputSource,
                'structure_source' => $p['structure_source'] ?? 'local',
                'is_manual' => false,
                'needs_review' => true,
                'confidence' => $p['confidence'],
                'suggested_date' => $p['date'],
                'suggested_amount' => $p['amount'] !== null && preg_match('/^\d{1,16}(\.\d{1,2})?$/', $p['amount']) ? $p['amount'] : null,
                'suggested_currency' => $currency,
                'suggested_currency_source' => $currencySource,
                'suggested_counterparty' => $p['counterparty'],
                'suggested_transaction_type' => $suggestedType,
                'suggested_note' => $p['note'] ?? null,
                'suggested_items' => $p['items'] ?? [],
                'items_mismatch' => (bool) ($p['items_mismatch'] ?? false),
                'suggested_amount_warning' => $p['amount_warning'] ?? null,
                'suggested_date_warning' => SuggestedDateWarning::for($p['date'] ?? null),
                'raw_text' => $text,
            ],
            'type' => $type,
            'transaction_type_options' => $typeOptions,
            'category_suggestion' => $suggestion ? ['category' => $suggestion->category, 'label' => $allowed[$suggestion->category], 'needs_review' => true] : null,
            'options' => [
                'types' => $typeOptions,
                'payment_statuses' => collect(PaymentStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
                'currencies' => collect(app(Currencies::class)->enabled())->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all(),
                'default_currency' => app(Currencies::class)->default(),
                'categories' => collect(config('bookkeeping.categories'))->map(fn ($items) => collect($items)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all())->all(),
            ],
            'ai_available' => $text !== null,
            'message' => match (true) {
                $text !== null => 'هذه مسودة مقترحة من تسجيلك الصوتي. راجعها قبل الحفظ.',
                ($stt->failureReason ?? null) === 'encoding' => 'تعذّرت قراءة النص العربي من التسجيل (مشكلة ترميز). أدخل العملية يدوياً، وأبلغ الدعم التقني.',
                ($stt->failureReason ?? null) === 'credits' => 'تحويل الصوت غير متاح حالياً. استخدم التعرّف على الكلام من المتصفح أو الإدخال اليدوي.',
                default => 'تحويل الصوت إلى نص غير متاح حالياً. يمكنك إدخال العملية يدوياً.',
            },
            'confirm' => ['method' => 'post', 'url' => route('enterprises.transactions.store', $enterprise)],
        ]);
    }
}
