<?php

namespace App\Actions\Documents;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Document;
use App\Models\Enterprise;
use App\Models\FinancialTransaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StoreDocumentAction
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(User $actor, Enterprise $enterprise, UploadedFile $file, ?string $title, ?FinancialTransaction $transaction): Document
    {
        abort_unless($actor->can('upload', [Document::class, $enterprise]), 403);
        abort_if($transaction !== null && $transaction->enterprise_id !== $enterprise->id, 404);

        $disk = config('documents.disk');
        $extension = strtolower($file->guessExtension() ?: $file->extension());
        // Random server-side name; the original filename is never trusted.
        $path = $file->storeAs('enterprises/'.$enterprise->id.'/documents', Str::random(40).'.'.$extension, $disk);

        try {
            return DB::transaction(function () use ($actor, $enterprise, $file, $title, $transaction, $disk, $path): Document {
                $document = new Document;
                $document->enterprise_id = $enterprise->id;
                $document->financial_transaction_id = $transaction?->id;
                $document->title = filled($title) ? $title : Str::limit($file->getClientOriginalName(), 140, '');
                $document->original_name = Str::limit($file->getClientOriginalName(), 250, '');
                $document->disk = $disk;
                $document->path = $path;
                $document->mime_type = (string) $file->getMimeType();
                $document->size = (int) $file->getSize();
                $document->uploaded_by = $actor->id;
                $document->save();

                $this->audit->record($actor, 'document.uploaded', $document, [
                    'enterprise_id' => $enterprise->id,
                    'financial_transaction_id' => $transaction?->id,
                    'mime_type' => $document->mime_type,
                    'size' => $document->size,
                ]);

                return $document;
            });
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }
}
