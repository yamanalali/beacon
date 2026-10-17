<?php

namespace App\Http\Controllers;

use App\Actions\Bookkeeping\ApproveTransactionAction;
use App\Actions\Bookkeeping\CorrectTransactionAction;
use App\Actions\Bookkeeping\CreateTransactionAction;
use App\Actions\Bookkeeping\RecordSettlementAction;
use App\Ai\Support\PaginatedProps;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Requests\Bookkeeping\CorrectTransactionRequest;
use App\Http\Requests\Bookkeeping\StoreSettlementRequest;
use App\Http\Requests\Bookkeeping\StoreTransactionRequest;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\TransactionItem;
use App\Models\TransactionSettlement;
use App\Navigation\PageAccess;
use App\Support\Currencies;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * Navigation entry point: SME users go to their own enterprise,
     * Beacon staff choose an enterprise first.
     */
    public function entry(Request $request): RedirectResponse
    {
        // Same gate the sidebar uses (auth.can.*): only a user with an own enterprise has this page.
        Gate::authorize(PageAccess::TRANSACTIONS_ENTRY);

        return redirect()->route('enterprises.transactions.index', PageAccess::ownEnterprise($request->user()));
    }

    public function index(Request $request, Enterprise $enterprise): Response
    {
        Gate::authorize('viewAny', [FinancialTransaction::class, $enterprise]);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(TransactionType::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'show_corrected' => ['nullable', 'boolean'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));

        $transactions = FinancialTransaction::query()
            ->where('enterprise_id', $enterprise->id)
            ->when(! ($filters['show_corrected'] ?? false), fn ($q) => $q->whereIn('status', [TransactionStatus::Posted, TransactionStatus::PendingApproval]))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['payment_status'] ?? null, fn ($q, $status) => $q->where('payment_status', $status))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereDate('transaction_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereDate('transaction_date', '<=', $to))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('counterparty', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%');
                });
            })
            ->with('settlements')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (FinancialTransaction $t): array => $this->row($t));

        return Inertia::render('Transactions/Index', [
            'enterprise' => $this->enterpriseProps($enterprise),
            'transactions' => PaginatedProps::from($transactions),
            'filters' => [
                'search' => $search,
                'type' => $filters['type'] ?? '',
                'payment_status' => $filters['payment_status'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
                'show_corrected' => (bool) ($filters['show_corrected'] ?? false),
            ],
            'options' => $this->options(),
            'can_create' => $request->user()->can('create', [FinancialTransaction::class, $enterprise]),
        ]);
    }

    public function create(Enterprise $enterprise): Response
    {
        Gate::authorize('create', [FinancialTransaction::class, $enterprise]);

        return Inertia::render('Transactions/Form', [
            'enterprise' => $this->enterpriseProps($enterprise),
            'options' => $this->options(),
            'original' => null,
        ]);
    }

    public function approve(Request $request, FinancialTransaction $transaction, ApproveTransactionAction $action): RedirectResponse
    {
        $action->execute($request->user(), $transaction);

        return back()->with('success', 'تم اعتماد العملية وإضافتها للتقارير.');
    }

    public function store(StoreTransactionRequest $request, Enterprise $enterprise, CreateTransactionAction $action): RedirectResponse
    {
        Gate::authorize('create', [FinancialTransaction::class, $enterprise]);

        $transaction = $action->execute($request->user(), $enterprise, $request->validated());

        return self::withItemsWarning(redirect()->route('transactions.show', $transaction)->with('success', 'تم حفظ العملية.'), $transaction);
    }

    public function show(Request $request, FinancialTransaction $transaction): Response
    {
        $transaction->load(['enterprise', 'settlements.creator:id,name', 'creator:id,name', 'correctedTransaction', 'correction', 'documents', 'items']);
        Gate::authorize('view', $transaction);

        $user = $request->user();

        return Inertia::render('Transactions/Show', [
            'enterprise' => $this->enterpriseProps($transaction->enterprise),
            'transaction' => $this->row($transaction) + [
                'creator_name' => $transaction->creator?->name,
                'correction_reason' => $transaction->correction_reason,
                'created_at' => $transaction->created_at?->toDateTimeString(),
                'corrects_public_id' => $transaction->correctedTransaction?->public_id,
                'corrected_by_public_id' => $transaction->correction?->public_id,
            ] + self::itemsProps($transaction),
            'settlements' => $transaction->settlements
                ->sortBy('settlement_date')
                ->values()
                ->map(fn (TransactionSettlement $s): array => [
                    'id' => $s->id,
                    'settlement_date' => $s->settlement_date?->toDateString(),
                    'amount' => (string) $s->amount,
                    'note' => $s->note,
                    'creator_name' => $s->creator?->name,
                ]),
            'documents' => $transaction->documents->whereNull('archived_at')->values()->map(fn (Document $d): array => DocumentController::row($d)),
            'can_approve' => $user->can('approve', $transaction),
            'can_correct' => $user->can('correct', $transaction),
            'can_settle' => $user->can('settle', $transaction) && $transaction->outstandingMinor() > 0,
            'can_upload' => $user->can('upload', [Document::class, $transaction->enterprise]),
        ]);
    }

    public function editCorrection(FinancialTransaction $transaction): Response
    {
        $transaction->load('enterprise');
        Gate::authorize('correct', $transaction);

        return Inertia::render('Transactions/Form', [
            'enterprise' => $this->enterpriseProps($transaction->enterprise),
            'options' => $this->options($transaction->currency),
            'original' => $this->row($transaction->load(['settlements', 'items'])) + self::itemsProps($transaction),
        ]);
    }

    public function correct(CorrectTransactionRequest $request, FinancialTransaction $transaction, CorrectTransactionAction $action): RedirectResponse
    {
        $transaction->load('enterprise');
        Gate::authorize('correct', $transaction);

        $corrected = $action->execute($request->user(), $transaction, $request->validated());

        return self::withItemsWarning(redirect()->route('transactions.show', $corrected)->with('success', 'تم حفظ التصحيح مع الاحتفاظ بالسجل الأصلي.'), $corrected);
    }

    public function storeSettlement(StoreSettlementRequest $request, FinancialTransaction $transaction, RecordSettlementAction $action): RedirectResponse
    {
        $transaction->load('enterprise');
        Gate::authorize('settle', $transaction);

        $action->execute($request->user(), $transaction, $request->validated());

        return redirect()->route('transactions.show', $transaction)->with('success', 'تم تسجيل الدفعة.');
    }

    /**
     * @return array<string, mixed>
     */
    private function row(FinancialTransaction $t): array
    {
        $categories = config('bookkeeping.categories.'.$t->type->value, []);

        return [
            'public_id' => $t->public_id,
            'type' => $t->type->value,
            'type_label' => $t->type->label(),
            'is_inflow' => $t->type->isInflow(),
            'transaction_date' => $t->transaction_date?->toDateString(),
            'amount' => (string) $t->amount,
            'currency' => $t->currency,
            'category' => $t->category,
            'category_label' => $categories[$t->category] ?? $t->category,
            'payment_status' => $t->payment_status->value,
            'payment_status_label' => $t->payment_status->label(),
            'outstanding' => Money::fromMinor($t->outstandingMinor()),
            'counterparty' => $t->counterparty,
            'description' => $t->description,
            'status' => $t->status->value,
            'status_label' => $t->status->label(),
            'is_superseded' => $t->status === TransactionStatus::Superseded,
        ];
    }

    /**
     * Line items as numbers plus the mismatch warning (never blocks saving).
     *
     * @return array{items: list<array<string, mixed>>, items_total: float|null, items_mismatch: bool}
     */
    public static function itemsProps(FinancialTransaction $t): array
    {
        $t->loadMissing('items');

        return [
            'items' => $t->items->map(fn (TransactionItem $i): array => $i->toProps())->values()->all(),
            'items_total' => $t->items->isEmpty() ? null : (float) Money::fromMinor($t->itemsTotalMinor()),
            'items_mismatch' => $t->itemsMismatch(),
        ];
    }

    public static function withItemsWarning(RedirectResponse $response, FinancialTransaction $t): RedirectResponse
    {
        $t->load('items');

        return $t->itemsMismatch()
            ? $response->with('warning', 'مجموع البنود ('.Money::fromMinor($t->itemsTotalMinor()).') لا يساوي مبلغ العملية ('.Money::fromMinor($t->amountMinor()).'). تم الحفظ، راجع الفرق (ضريبة أو خصم مثلاً).')
            : $response;
    }

    /**
     * @return array<string, string>
     */
    private function enterpriseProps(Enterprise $enterprise): array
    {
        return [
            'public_id' => $enterprise->public_id,
            'business_name' => $enterprise->business_name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(?string $historicalCurrency = null): array
    {
        $pairs = fn (array $items): array => collect($items)
            ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
            ->values()
            ->all();

        return [
            'types' => collect(TransactionType::cases())->map(fn (TransactionType $t) => ['value' => $t->value, 'label' => $t->label()])->all(),
            'payment_statuses' => collect(PaymentStatus::cases())->map(fn (PaymentStatus $s) => ['value' => $s->value, 'label' => $s->label()])->all(),
            'currencies' => $pairs(app(Currencies::class)->available($historicalCurrency)),
            'default_currency' => app(Currencies::class)->default(),
            'categories' => collect(config('bookkeeping.categories'))->map($pairs)->all(),
        ];
    }
}
