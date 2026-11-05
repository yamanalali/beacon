<?php

namespace App\Http\Controllers;

use App\Actions\Audit\RecordAuditLog;
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
use App\Support\TabularListExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $transactions = $this->filteredTransactions($enterprise, $filters)
            ->with('settlements')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (FinancialTransaction $t): array => $this->row($t));

        return Inertia::render('Transactions/Index', [
            'enterprise' => $this->enterpriseProps($enterprise),
            'transactions' => PaginatedProps::from($transactions),
            'summary' => $this->summary($enterprise, $filters),
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
            'can_export' => $request->user()->can('export', [FinancialTransaction::class, $enterprise]),
        ]);
    }

    public function export(Request $request, Enterprise $enterprise, string $format, TabularListExporter $exporter, RecordAuditLog $audit): StreamedResponse
    {
        Gate::authorize('export', [FinancialTransaction::class, $enterprise]);
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(TransactionType::class)],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'show_corrected' => ['nullable', 'boolean'],
        ]);

        $headers = ['المعرف', 'التاريخ', 'النوع', 'التصنيف', 'المبلغ', 'العملة', 'الجهة', 'حالة الدفع', 'الحالة'];
        $rows = $this->filteredTransactions($enterprise, $filters)
            ->with('settlements')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(10000)
            ->get()
            ->map(function (FinancialTransaction $t): array {
                $row = $this->row($t);

                return [
                    $row['public_id'],
                    $row['transaction_date'],
                    $row['type_label'],
                    $row['category_label'],
                    $row['amount'],
                    $row['currency'],
                    $row['counterparty'] ?? '',
                    $row['payment_status_label'],
                    $row['status_label'],
                ];
            })
            ->all();

        $audit->record($request->user(), 'transactions.list.exported', $enterprise, [
            'format' => $format,
            'filters' => $filters,
            'row_count' => count($rows),
        ]);

        $filename = 'transactions-'.$enterprise->public_id.'-'.now()->format('Ymd');

        return $format === 'csv'
            ? $exporter->csv($headers, $rows, $filename)
            : $exporter->xlsx($headers, $rows, $filename, 'المعاملات');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredTransactions(Enterprise $enterprise, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return FinancialTransaction::query()
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
            });
    }

    /**
     * Income / expense / remaining for the current filters.
     * Posted rows only (authoritative). Never converts between currencies.
     *
     * @return array{currency: string, income: string, expense: string, remaining: string, count: int, by_currency: list<array{currency: string, income: string, expense: string, remaining: string, count: int}>}
     */
    private function summary(Enterprise $enterprise, array $filters): array
    {
        $default = app(Currencies::class)->default();
        $rows = $this->filteredTransactions($enterprise, $filters)
            ->where('status', TransactionStatus::Posted)
            ->groupBy('currency', 'type')
            ->selectRaw('currency, type, COUNT(*) as row_count, SUM(amount) as total')
            ->toBase()
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $bucket = &$totals[$row->currency];
            $bucket ??= ['income' => 0, 'expense' => 0, 'count' => 0];
            $type = TransactionType::tryFrom((string) $row->type);
            $minor = Money::toMinor(is_string($row->total) ? $row->total : number_format((float) $row->total, 2, '.', ''));
            if ($type?->isInflow()) {
                $bucket['income'] += $minor;
            } elseif ($type !== null) {
                $bucket['expense'] += $minor;
            }
            $bucket['count'] += (int) $row->row_count;
            unset($bucket);
        }

        $currencies = array_values(array_unique(array_merge([$default], array_keys($totals))));
        $byCurrency = [];
        foreach ($currencies as $currency) {
            $t = $totals[$currency] ?? ['income' => 0, 'expense' => 0, 'count' => 0];
            if ($t['count'] === 0 && $currency !== $default && ! isset($totals[$currency])) {
                continue;
            }
            $byCurrency[] = [
                'currency' => $currency,
                'income' => Money::fromMinor($t['income']),
                'expense' => Money::fromMinor($t['expense']),
                'remaining' => Money::fromMinor($t['income'] - $t['expense']),
                'count' => $t['count'],
            ];
        }

        // Prefer the default currency card; if it has no rows, use the first currency that does.
        $primary = collect($byCurrency)->first(fn (array $c) => $c['currency'] === $default && $c['count'] > 0)
            ?? collect($byCurrency)->first(fn (array $c) => $c['count'] > 0)
            ?? ['currency' => $default, 'income' => Money::fromMinor(0), 'expense' => Money::fromMinor(0), 'remaining' => Money::fromMinor(0), 'count' => 0];

        return [
            'currency' => $primary['currency'],
            'income' => $primary['income'],
            'expense' => $primary['expense'],
            'remaining' => $primary['remaining'],
            'count' => $primary['count'],
            'by_currency' => $byCurrency,
        ];
    }

    public function create(Request $request, Enterprise $enterprise): Response
    {
        Gate::authorize('create', [FinancialTransaction::class, $enterprise]);

        $input = $request->validate(['copy_from' => ['nullable', 'string', 'max:26']]);
        $template = empty($input['copy_from']) ? null : FinancialTransaction::query()->posted()
            ->where('enterprise_id', $enterprise->id)->where('public_id', $input['copy_from'])->with(['items', 'settlements'])->firstOrFail();

        return Inertia::render('Transactions/Form', [
            'enterprise' => $this->enterpriseProps($enterprise),
            'options' => $this->options(),
            'original' => null,
            'template' => $template ? $this->row($template) + self::itemsProps($template) : null,
            'counterparties' => $this->counterparties($enterprise),
            'requires_approval' => $request->user()->permission('require_approval', false),
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
            'can_create' => $user->can('create', [FinancialTransaction::class, $transaction->enterprise]),
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
            'counterparties' => $this->counterparties($transaction->enterprise),
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
     * @return list<string>
     */
    private function counterparties(Enterprise $enterprise): array
    {
        return FinancialTransaction::query()->posted()->where('enterprise_id', $enterprise->id)
            ->whereNotNull('counterparty')->where('counterparty', '!=', '')
            ->distinct()->orderBy('counterparty')->limit(100)->pluck('counterparty')->all();
    }

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
