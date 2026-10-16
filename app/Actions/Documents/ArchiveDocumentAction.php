<?php

namespace App\Actions\Documents;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Document;
use App\Models\User;

/**
 * Archives (hides) a document. The file and record are kept; nothing is
 * physically deleted.
 *
 * TODO: BUSINESS DECISION REQUIRED
 * Document retention periods and whether/when archived files may be
 * permanently deleted are not defined.
 */
class ArchiveDocumentAction
{
    public function __construct(private readonly RecordAuditLog $audit) {}

    public function execute(User $actor, Document $document, string $reason): void
    {
        abort_unless($actor->can('archive', $document), 403);
        abort_if($document->isArchived(), 409);

        $document->archived_at = now();
        $document->archived_by = $actor->id;
        $document->archive_reason = $reason;
        $document->save();

        $this->audit->record($actor, 'document.archived', $document, [
            'enterprise_id' => $document->enterprise_id,
            'reason' => $reason,
        ]);
    }
}
