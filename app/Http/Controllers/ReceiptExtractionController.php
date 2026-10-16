<?php

namespace App\Http\Controllers;

use App\Ai\Support\PaginatedProps;
use App\Actions\Bookkeeping\ConfirmReceiptExtractionAction;
use App\Actions\Bookkeeping\StartReceiptExtractionAction;
use App\Contracts\TransactionCategorizer;
use App\Enums\ExtractionStatus;
use App\Enums\PaymentStatus;
use App\Enums\TransactionType;
use App\Finance\TransactionTypeSuggester;
use App\Http\Requests\Bookkeeping\StoreTransactionRequest;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\ReceiptExtraction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReceiptExtractionController extends Controller
{
    public function store(Request $request, Document $document, StartReceiptExtractionAction $action): RedirectResponse
    {
        $document->load('enterprise');
        abort_unless($action->allows($request->user(), $document), 403);

        $extraction = $action->execute($request->user(), $document);

        return redirect()->route('extractions.show', $extraction);
    }

    public function pending(Request $request, Enterprise $enterprise): Response
    {
        // Same ability as extractions.show: membership/role checked by EnterprisePolicy::view.
        Gate::authorize('view', $enterprise);

        $extractions = ReceiptExtraction::query()
            ->where('enterprise_id', $enterprise->id)
            ->pendingReview()
            ->with('document:id,public_id,title')
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ReceiptExtraction $e): array => [
                'public_id' => $e->public_id,
                'document_title' => $e->document?->title,
                'created_at' => $e->created_at?->toDateString(),
                'suggested_date' => $e->suggested_date?->toDateString(),
                'suggested_amount' => $e->suggested_amount,
                'suggested_currency' => $e->suggested_currency,
                'suggested_counterparty' => $e->suggested_counterparty,
                'needs_review' => (bool) $e->needs_review,
                'confidence' => $e->confidence,
            ]);

        return Inertia::render('Extractions/Pending', [
            'enterprise' => ['public_id' => $enterprise->public_id, 'business_name' => $enterprise->business_name],
            'extractions' => PaginatedProps::from($extractions),
        ]);
    }

    public function show(Request $request, ReceiptExtraction $extraction, TransactionCategorizer $categorizer): Response
    {
        $extraction->load(['enterprise', 'document', 'confirmedTransaction']);
        Gate::authorize('view', $extraction->enterprise);

        $suggestedType = TransactionType::tryFrom((string) $extraction->suggested_transaction_type)?->value;
        // Category list follows the chosen type: explicit ?type= wins, else the suggestion.
        $type = $request->string('type')->toString() ?: ($suggestedType ?? 'expense');
        $type = TransactionType::tryFrom($type)?->value ?? 'expense';
        $allowed = config('bookkeeping.categories.'.$type, []);

        $suggestion = $extraction->status === ExtractionStatus::PendingReview
            ? $categorizer->suggest($type, null, $extraction->suggested_counterparty, $allowed)
            : null;
        // Only categories from the configured list are ever offered.
        if ($suggestion !== null && ! array_key_exists($suggestion->category, $allowed)) {
            $suggestion = null;
        }

        return Inertia::render('Extractions/Show', [
            'enterprise' => ['public_id' => $extraction->enterprise->public_id, 'business_name' => $extraction->enterprise->business_name],
            'extraction' => [
                'public_id' => $extraction->public_id,
                'status' => $extraction->status->value,
                'status_label' => $extraction->status->label(),
                'extractor' => $extraction->extractor,
                'is_manual' => $extraction->extractor === 'manual',
                'needs_review' => $extraction->needs_review,
                'confidence' => $extraction->confidence,
                'suggested_date' => $extraction->suggested_date?->toDateString(),
                'suggested_amount' => $extraction->suggested_amount,
                'suggested_currency' => $extraction->suggested_currency,
                'suggested_counterparty' => $extraction->suggested_counterparty,
                'document' => DocumentController::row($extraction->document),
                'confirmed_transaction_public_id' => $extraction->confirmedTransaction?->public_id,
            ],
            'type' => $type,
            'suggested_transaction_type' => $suggestedType,
            'transaction_type_options' => TransactionTypeSuggester::options(),
            'category_suggestion' => $suggestion ? [
                'category' => $suggestion->category,
                'label' => $allowed[$suggestion->category],
                'needs_review' => $suggestion->needsReview,
            ] : null,
            'options' => [
                'types' => collect(TransactionType::cases())->map(fn ($t) => ['value' => $t->value, 'label' => $t->label()])->all(),
                'payment_statuses' => collect(PaymentStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
                'currencies' => collect(config('bookkeeping.currencies'))->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all(),
                'default_currency' => config('bookkeeping.default_currency'),
                'categories' => collect(config('bookkeeping.categories'))->map(fn ($items) => collect($items)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all())->all(),
            ],
            'can_review' => $extraction->status === ExtractionStatus::PendingReview
                && $request->user()->can('create', [FinancialTransaction::class, $extraction->enterprise]),
        ]);
    }

    public function confirm(StoreTransactionRequest $request, ReceiptExtraction $extraction, ConfirmReceiptExtractionAction $action): RedirectResponse
    {
        $extraction->load(['enterprise', 'document']);
        Gate::authorize('create', [FinancialTransaction::class, $extraction->enterprise]);

        $transaction = $action->confirm($request->user(), $extraction, $request->validated());

        return redirect()->route('transactions.show', $transaction)->with('success', 'تم حفظ العملية بعد مراجعتك.');
    }

    public function reject(Request $request, ReceiptExtraction $extraction, ConfirmReceiptExtractionAction $action): RedirectResponse
    {
        $extraction->load('enterprise');
        Gate::authorize('create', [FinancialTransaction::class, $extraction->enterprise]);

        $action->reject($request->user(), $extraction);

        return redirect()->route('enterprises.documents.index', $extraction->enterprise)->with('success', 'تم تجاهل الاقتراح.');
    }
}
