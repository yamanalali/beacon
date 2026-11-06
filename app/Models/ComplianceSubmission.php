<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceSubmission extends Model
{
    public const REVIEW_PENDING = 'pending';

    public const REVIEW_APPROVED = 'approved';

    public const REVIEW_REJECTED = 'rejected';

    public const REVIEW_RESUBMISSION_REQUESTED = 'resubmission_requested';

    public const REVIEW_STATUSES = [
        self::REVIEW_PENDING => 'بانتظار المراجعة',
        self::REVIEW_APPROVED => 'معتمد',
        self::REVIEW_REJECTED => 'مرفوض',
        self::REVIEW_RESUBMISSION_REQUESTED => 'مطلوب وثيقة أخرى',
    ];

    protected $fillable = [
        'enterprise_compliance_record_id',
        'document_id',
        'submitted_by',
        'submitted_at',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function record(): BelongsTo
    {
        return $this->belongsTo(EnterpriseComplianceRecord::class, 'enterprise_compliance_record_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->review_status === self::REVIEW_PENDING;
    }
}
