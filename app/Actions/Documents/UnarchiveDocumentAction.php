<?php

namespace App\Actions\Documents;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Document;
use App\Models\User;

/**
 * Restores an archived document to the normal lists. The previous archive
 * reason is kept in the audit log.
 */
class UnarchiveDocumentAction
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(User $actor, Document $document): void
    {
        abort_unless($actor->can('unarchive', $document), 403);

        $previous = [
            'archived_at' => $document->archived_at?->toDateTimeString(),
            'archived_by' => $document->archived_by,
            'reason' => $document->archive_reason,
        ];

        $document->archived_at = null;
        $document->archived_by = null;
        $document->archive_reason = null;
        $document->save();

        $this->audit->record($actor, 'document.unarchived', $document, [
            'enterprise_id' => $document->enterprise_id,
            'previous' => $previous,
        ]);
    }
}
