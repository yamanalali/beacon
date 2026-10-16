<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRun extends Model
{
    public const TYPE_BACKUP = 'backup';
    public const TYPE_RESTORE_TEST = 'restore_test';

    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED = 'failed';

    public const STATUS_LABELS = ['running' => 'قيد التنفيذ', 'succeeded' => 'ناجحة', 'failed' => 'فشلت'];
    public const TYPE_LABELS = ['backup' => 'نسخة احتياطية', 'restore_test' => 'اختبار استعادة'];

    protected $fillable = [
        'type', 'status', 'backup_run_id', 'file_path', 'size_bytes', 'checksum_sha256', 'details',
        'error', 'triggered_by', 'started_at', 'finished_at', 'pruned_at',
    ];

    protected function casts(): array
    {
        return ['details' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'pruned_at' => 'datetime'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'backup_run_id');
    }

    public function trigger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }
}
