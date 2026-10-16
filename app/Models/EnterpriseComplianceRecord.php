<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnterpriseComplianceRecord extends Model
{
    // TODO: BUSINESS DECISION REQUIRED - confirm the list of compliance completion statuses.
    public const STATUSES = [
        'not_started' => 'لم يبدأ',
        'in_progress' => 'قيد التنفيذ',
        'completed' => 'مكتمل',
    ];

    protected $fillable = [
        'enterprise_id', 'compliance_requirement_id', 'compliance_requirement_version_id',
        'status', 'due_date', 'notes', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ComplianceRequirementVersion::class, 'compliance_requirement_version_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
