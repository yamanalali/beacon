<?php

namespace App\Http\Controllers;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Bookkeeping\StartReceiptExtractionAction;
use App\Actions\Documents\ArchiveDocumentAction;
use App\Actions\Documents\StoreDocumentAction;
use App\Actions\Documents\UnarchiveDocumentAction;
use App\Actions\Documents\UpdateDocumentMetadataAction;
use App\Ai\Support\PaginatedProps;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Navigation\PageAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function entry(Request $request): RedirectResponse
    {
        // Same gate the sidebar uses (auth.can.*): only a user with an own enterprise has this page.
        Gate::authorize(PageAccess::DOCUMENTS_ENTRY);

        return redirect()->route('enterprises.documents.index', PageAccess::ownEnterprise($request->user()));
    }

    public function index(Request $request, Enterprise $enterprise): Response
    {
        Gate::authorize('viewAny', [Document::class, $enterprise]);

        $search = trim((string) $request->input('search', ''));

        $documents = Document::query()
            ->active()
            ->where('enterprise_id', $enterprise->id)
            ->with(['transaction:id,public_id', 'uploader:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function ($inner) use ($like): void {
                    $inner->where('title', 'like', $like)
                        ->orWhere('original_name', 'like', $like);
                });
            })
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
            'filters' => ['search' => $search],
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

                return redirect()->route('extractions.show', $extraction)->with('success', ui('flash.document_uploaded_ocr_review'));
            }

            return back()->with('success', ui('flash.document_uploaded'))
                ->with('info', ui('flash.document_uploaded_no_invoice_ocr'));
        }

        return back()->with('success', ui('flash.document_uploaded'));
    }

    public function preview(Request $request, Document $document, RecordAuditLog $audit): StreamedResponse
    {
        $document->load('enterprise');
        Gate::authorize('view', $document);
        abort_unless($document->isPreviewable(), 404);

        $disk = Storage::disk($document->disk);
        abort_unless($disk->exists($document->path), 404);

        // Refuse to stream a body that does not match the stored document type (avoids
        // accidental script/HTML payloads in the preview tab). Details go to the log only.
        $head = $disk->readStream($document->path);
        abort_unless(is_resource($head), 404);
        $bytes = fread($head, 64) ?: '';
        if (is_resource($head)) {
            fclose($head);
        }
        if ($this->looksLikeUnsafePreviewPayload($bytes, $document->mime_type)) {
            Log::warning('document.preview.blocked_unsafe_payload', [
                'document_id' => $document->id,
                'public_id' => $document->public_id,
                'mime_type' => $document->mime_type,
            ]);
            abort(404);
        }

        $audit->record($request->user(), 'document.previewed', $document);

        return $disk->response($document->path, $this->safeName($document), [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    public function download(Request $request, Document $document, RecordAuditLog $audit): StreamedResponse
    {
        abort_unless($request->user()->permission('export', true), 403);
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

        $history = AuditLog::query()
            ->where('target_type', $document->getMorphClass())
            ->where('target_id', (string) $document->id)
            ->with('actor:id,name')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (AuditLog $log): array => [
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
            'can_unarchive' => $request->user()->can('unarchive', $document),
        ]);
    }

    public function update(Request $request, Document $document, UpdateDocumentMetadataAction $action): RedirectResponse
    {
        $document->load('enterprise');
        Gate::authorize('update', $document);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'transaction' => ['nullable', 'string', 'size:26'],
        ], ['title.required' => ui('validation.document_title_required')]);

        $transaction = filled($data['transaction'] ?? null)
            ? FinancialTransaction::query()->where('public_id', $data['transaction'])->where('enterprise_id', $document->enterprise_id)->firstOrFail()
            : null;

        $action->execute($request->user(), $document, $data['title'], $transaction);

        return back()->with('success', ui('flash.document_metadata_saved'));
    }

    public function archive(Request $request, Document $document, ArchiveDocumentAction $action): RedirectResponse
    {
        $document->load('enterprise');
        Gate::authorize('archive', $document);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], ['reason.required' => ui('validation.archive_reason_required'), 'reason.min' => ui('validation.archive_reason_min')]);

        $action->execute($request->user(), $document, $data['reason']);

        return redirect()->route('enterprises.documents.index', $document->enterprise)->with('success', ui('flash.document_archived'));
    }

    public function unarchive(Request $request, Document $document, UnarchiveDocumentAction $action): RedirectResponse
    {
        $document->load('enterprise');
        Gate::authorize('unarchive', $document);

        $action->execute($request->user(), $document);

        return redirect()->route('documents.show', $document)->with('success', ui('flash.document_unarchived'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(Document $document): array
    {
        $user = request()->user();

        return [
            'public_id' => $document->public_id,
            'can_download' => $user?->permission('export', true) ?? false,
            'can_archive' => $user?->can('archive', $document) ?? false,
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

    /**
     * Detect script/HTML bodies that must never be shown as a document preview.
     */
    private function looksLikeUnsafePreviewPayload(string $head, string $mimeType): bool
    {
        $trim = ltrim($head);
        if ($trim === '') {
            return true;
        }

        if (preg_match('/^(?:import\s|export\s|const\s|let\s|var\s|function\s|<!DOCTYPE|<html\b|<\?php)/i', $trim) === 1) {
            return true;
        }

        if (str_starts_with($mimeType, 'image/')) {
            $isImage = str_starts_with($head, "\xFF\xD8\xFF") // jpeg
                || str_starts_with($head, "\x89PNG\r\n\x1A\n") // png
                || str_starts_with($head, 'GIF87a')
                || str_starts_with($head, 'GIF89a')
                || str_starts_with($head, 'RIFF'); // webp container

            return ! $isImage;
        }

        if ($mimeType === 'application/pdf') {
            return ! str_starts_with($trim, '%PDF');
        }

        return false;
    }
}
