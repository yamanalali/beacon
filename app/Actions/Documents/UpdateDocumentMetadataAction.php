<?php

namespace App\Actions\Documents;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Document;
use App\Models\FinancialTransaction;
use App\Models\User;

class UpdateDocumentMetadataAction
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(User $actor, Document $document, string $title, ?FinancialTransaction $transaction): void
    {
        abort_unless($actor->can('update', $document), 403);
        abort_if($transaction !== null && $transaction->enterprise_id !== $document->enterprise_id, 404);

        $before = ['title' => $document->title, 'financial_transaction_id' => $document->financial_transaction_id];

        $document->title = $title;
        $document->financial_transaction_id = $transaction?->id;
        $document->save();

        $after = ['title' => $document->title, 'financial_transaction_id' => $document->financial_transaction_id];

        if ($before !== $after) {
            $this->audit->record($actor, 'document.metadata_updated', $document, ['before' => $before, 'after' => $after]);
        }
    }
}
