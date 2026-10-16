<?php

namespace App\Http\Controllers;

use App\Actions\Audit\RecordAuditLog;
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
            ->where('enterprise_id', $enterprise->id)
            ->with(['transaction:id,public_id', 'uploader:id,name'])
            ->latest()
            ->latest('id')
            ->paginate(20)
            ->through(fn (Document $d): array => self::row($d));

        return Inertia::render('Documents/Index', [
            'enterprise' => [
                'public_id' => $enterprise->public_id,
                'business_name' => $enterprise->business_name,
            ],
            'documents' => $documents,
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

        $action->execute($request->user(), $enterprise, $request->file('file'), $request->input('title'), $transaction);

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
