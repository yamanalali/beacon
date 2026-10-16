<?php

namespace App\Http\Controllers;

use App\Ai\Support\PaginatedProps;
use App\Actions\Audit\RecordAuditLog;
use App\Actions\Bookkeeping\StartReceiptExtractionAction;
use App\Actions\Documents\StoreDocumentAction;
use App\Enums\UserRole;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function entry(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->role === UserRole::SmeUser) {
            $enterprise = Enterprise::query()
                ->whereHas('members', fn ($query) => $query->whereKey($user->id))
                ->first();

            return $enterprise !== null
                ? redirect()->route('enterprises.documents.index', $enterprise)
                : redirect('/');
        }

        Gate::authorize('viewAny', Enterprise::class);

        return redirect()->route('enterprises.index');
    }

    public function index(Request $request, Enterprise $enterprise): Response
    {
        Gate::authorize('viewAny', [Document::class, $enterprise]);

        $documents = Document::query()
            ->active()
            ->where('enterprise_id', $enterprise->id)
            ->with(['transaction:id,public_id', 'uploader:id,name'])
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Document $d): array => self::row($d));

        return Inertia::render('Documents/Index', [
            'enterprise' => [
                'public_id' => $enterprise->public_id,
                'business_name' => $enterprise->business_name,
            ],
            'documents' => PaginatedProps::from($documents),
            'can_upload' => $request->user()->can('upload', [Document::class, $enterprise]),
            'can_extract' => $request->user()->can('create', [FinancialTransaction::class, $enterprise]),
        ]);
    }

    public function store(StoreDocumentRequest $request, Enterprise $enterprise, StoreDocumentAction $action): RedirectResponse
    {
        Gate::authorize('upload', [Document::class, $enterprise]);

        $transaction = null;

        if ($request->filled('transaction')) {
            $transaction = FinancialTransaction::query()
                ->where('public_id', $request->string('transaction'))
                ->where('enterprise_id', $enterprise->id)
                ->firstOrFail();
        }

        $document = $action->execute($request->user(), $enterprise, $request->file('file'), $request->input('title'), $transaction);

        // Always expose the new document's public id so the UI never has to diff lists.
        $request->session()->flash('created_document_id', $document->public_id);

        if ($request->boolean('extract')) {
            $extractor = app(StartReceiptExtractionAction::class);

            if ($extractor->allows($request->user(), $document)) {
                // Suggestion only: no transaction is created until the user confirms.
                $extraction = $extractor->execute($request->user(), $document);

                return redirect()->route('extractions.show', $extraction)->with('success', 'تم رفع المستند وقراءته. راجع الاقتراح قبل الحفظ.');
            }

            return back()->with('success', 'تم رفع المستند.')
                ->with('info', 'تم حفظ المستند، لكن لا يمكنك قراءته كفاتورة.');
        }

        return back()->with('success', 'تم رفع المستند.');
    }

    public function preview(Request $request, Document $document, RecordAuditLog $audit): StreamedResponse
    {
        $document->load('enterprise');
        Gate::authorize('view', $document);
        abort_unless($document->isPreviewable(), 404);

        $audit->record($request->user(), 'document.previewed', $document);

        return Storage::disk($document->disk)->response($document->path, $this->safeName($document), [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    public function download(Request $request, Document $document, RecordAuditLog $audit): StreamedResponse
    {
        $document->load('enterprise');
        Gate::authorize('view', $document);

        $audit->record($request->user(), 'document.downloaded', $document);

        return Storage::disk($document->disk)->download($document->path, $this->safeName($document), [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function show(Request $request, Document $document): Response
    {
        $document->load(['enterprise', 'transaction:id,public_id', 'uploader:id,name']);
        Gate::authorize('view', $document);

        $history = \App\Models\AuditLog::query()
            ->where('target_type', $document->getMorphClass())
            ->where('target_id', (string) $document->id)
            ->with('actor:id,name')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (\App\Models\AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'created_at' => $log->created_at?->toDateTimeString(),
                'actor_name' => $log->actor?->name,
            ]);

        $transactions = $request->user()->can('update', $document)
            ? FinancialTransaction::query()->posted()->where('enterprise_id', $document->enterprise_id)
                ->orderByDesc('transaction_date')->limit(100)->get()
                ->map(fn (FinancialTransaction $t): array => [
                    'value' => $t->public_id,
                    'label' => $t->transaction_date?->toDateString().' · '.$t->type->label().' · '.$t->amount.' '.$t->currency,
                ])
            : [];

        return Inertia::render('Documents/Show', [
            'enterprise' => ['public_id' => $document->enterprise->public_id, 'business_name' => $document->enterprise->business_name],
            'document' => self::row($document) + [
                'original_name' => $document->original_name,
                'is_archived' => $document->isArchived(),
                'archived_at' => $document->archived_at?->toDateTimeString(),
                'archive_reason' => $document->archive_reason,
            ],
            'history' => $history,
            'transactions' => $transactions,
            'can_update' => $request->user()->can('update', $document),
            'can_archive' => ! $document->isArchived() && $request->user()->can('archive', $document),
        ]);
    }

    public function update(Request $request, Document $document, \App\Actions\Documents\UpdateDocumentMetadataAction $action): RedirectResponse
    {
        $document->load('enterprise');
        Gate::authorize('update', $document);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'transaction' => ['nullable', 'string', 'size:26'],
        ], ['title.required' => 'عنوان المستند مطلوب.']);

        $transaction = filled($data['transaction'] ?? null)
            ? FinancialTransaction::query()->where('public_id', $data['transaction'])->where('enterprise_id', $document->enterprise_id)->firstOrFail()
            : null;

        $action->execute($request->user(), $document, $data['title'], $transaction);

        return back()->with('success', 'تم حفظ بيانات المستند.');
    }

    public function archive(Request $request, Document $document, \App\Actions\Documents\ArchiveDocumentAction $action): RedirectResponse
    {
        $document->load('enterprise');
        Gate::authorize('archive', $document);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], ['reason.required' => 'اكتب سبب الأرشفة.', 'reason.min' => 'اكتب سبب الأرشفة بشكل أوضح.']);

        $action->execute($request->user(), $document, $data['reason']);

        return redirect()->route('enterprises.documents.index', $document->enterprise)->with('success', 'تمت أرشفة المستند. الملف ما زال محفوظاً.');
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(Document $document): array
    {
        return [
            'public_id' => $document->public_id,
            'title' => $document->title,
            'mime_type' => $document->mime_type,
            'is_image' => str_starts_with($document->mime_type, 'image/'),
            'is_previewable' => $document->isPreviewable(),
            'size_kb' => (int) ceil($document->size / 1024),
            'uploaded_at' => $document->created_at?->toDateString(),
            'uploader_name' => $document->relationLoaded('uploader') ? $document->uploader?->name : null,
            'transaction_public_id' => $document->relationLoaded('transaction') ? $document->transaction?->public_id : null,
        ];
    }

    private function safeName(Document $document): string
    {
        $extension = pathinfo($document->path, PATHINFO_EXTENSION);

        return 'document-'.$document->public_id.($extension !== '' ? '.'.$extension : '');
    }
}
