<?php

namespace App\Ai\Http\Controllers;

use App\Ai\Voice\SpeechToText;
use App\Ai\Voice\VoiceDraftParser;
use App\Contracts\TransactionCategorizer;
use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Finance\ReceiptTextCategorizer;
use App\Finance\TransactionTypeSuggester;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Voice draft transaction: short audio -> transcript -> SUGGESTED transaction in
 * the same field shape as receipt extractions. Nothing is saved; the user reviews
 * and submits the normal transaction form (enterprises.transactions.store).
 * The recording is deleted right after transcription, also on failure.
 */
class VoiceDraftController extends Controller
{
    public const MIMES = ['audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/wav', 'audio/x-wav', 'audio/wave', 'video/webm'];

    public function store(Request $request, Enterprise $enterprise, SpeechToText $stt, VoiceDraftParser $parser, TransactionCategorizer $categorizer, TransactionTypeSuggester $typeSuggester, ReceiptTextCategorizer $textCategorizer): JsonResponse
    {
        abort_unless(config('ai.voice.enabled'), 404);
        abort_unless($request->user()->isActive(), 403);
        Gate::authorize('create', [FinancialTransaction::class, $enterprise]);

        $request->validate([
            'audio' => ['required', 'file', 'mimetypes:'.implode(',', self::MIMES), 'max:'.(int) config('ai.voice.max_kb', 5120)],
        ]);

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

        $p = $text !== null ? $parser->parse($text) : ['amount' => null, 'date' => null, 'counterparty' => null, 'currency' => null, 'confidence' => null];
        $suggestedType = $typeSuggester->suggest($text);
        // Categories follow the suggested type (as Extractions/Show does).
        $type = $suggestedType ?? 'expense';
        $allowed = config('bookkeeping.categories.'.$type, []);
        $suggestion = $text !== null ? $categorizer->suggest($type, $text, $p['counterparty'], $allowed) : null;
        // Fall back to the keyword categorizer used for receipts (e.g. «اجار» -> rent).
        if ($suggestion === null && $text !== null) {
            $suggestion = $textCategorizer->suggest($text, $allowed);
        }
        if ($suggestion !== null && ! array_key_exists($suggestion->category, $allowed)) {
            $suggestion = null;
        }
        $currencies = array_keys(config('bookkeeping.currencies'));
        $typeOptions = collect(TransactionType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->all();

        AuditLog::query()->create([
            'actor_id' => $request->user()->id, 'action' => 'ai.voice_draft.created',
            'target_type' => Enterprise::class, 'target_id' => $enterprise->id,
            'context' => ['stt' => $stt->name(), 'transcribed' => $text !== null], // no transcript stored
        ]);

        return response()->json([
            'enterprise' => ['public_id' => $enterprise->public_id, 'business_name' => $enterprise->business_name],
            'draft' => [
                'source' => 'voice',
                'extractor' => $stt->name(),
                'is_manual' => false,
                'needs_review' => true,
                'confidence' => $p['confidence'],
                'suggested_date' => $p['date'],
                'suggested_amount' => $p['amount'] !== null && preg_match('/^\d{1,16}(\.\d{1,2})?$/', $p['amount']) ? $p['amount'] : null,
                'suggested_currency' => in_array($p['currency'], $currencies, true) ? $p['currency'] : null,
                'suggested_counterparty' => $p['counterparty'],
                'suggested_transaction_type' => $suggestedType,
                'raw_text' => $text,
            ],
            'type' => $type,
            'transaction_type_options' => $typeOptions,
            'category_suggestion' => $suggestion ? ['category' => $suggestion->category, 'label' => $allowed[$suggestion->category], 'needs_review' => true] : null,
            'options' => [
                'types' => $typeOptions,
                'payment_statuses' => collect(PaymentStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
                'currencies' => collect(config('bookkeeping.currencies'))->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all(),
                'default_currency' => config('bookkeeping.default_currency'),
                'categories' => collect(config('bookkeeping.categories'))->map(fn ($items) => collect($items)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all())->all(),
            ],
            'ai_available' => $text !== null,
            'message' => $text !== null ? 'هذه مسودة مقترحة من تسجيلك الصوتي. راجعها قبل الحفظ.' : 'تحويل الصوت إلى نص غير متاح حالياً. يمكنك إدخال العملية يدوياً.',
            'confirm' => ['method' => 'post', 'url' => route('enterprises.transactions.store', $enterprise)],
        ]);
    }
}
