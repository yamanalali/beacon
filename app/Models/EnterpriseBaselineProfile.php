<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnterpriseBaselineProfile extends Model
{
    protected $fillable = [
        'enterprise_id',
        'external_mse_id',
        'source_uuid',
        'submission_time',
        'answers',
        'imported_at',
        'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'submission_time' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
