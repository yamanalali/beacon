<?php

namespace App\Ai\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Ai\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * Beacon-approved knowledge content (rules §37, beacon-platform §76).
 * Statuses: draft | published | archived. Each content edit bumps `version`.
 * Beacon owns/approves substantive content; none is seeded here.
 */
class KnowledgeArticle extends Model
{
    use HasPublicId;

    public const STATUSES = ['draft', 'published', 'archived'];

    /** Assistant topics from rules §34. */
    public const TOPICS = ['bookkeeping', 'platform_use', 'business_planning', 'compliance', 'financial_readiness'];

    protected $fillable = ['title', 'body', 'topic', 'effective_from', 'effective_until'];

    protected $hidden = ['id', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
            'version' => 'integer',
        ];
    }

    /** Published and within its effective window. */
    public function scopeCurrentlyApproved(Builder $q): Builder
    {
        $today = now()->toDateString();

        return $q->where('status', 'published')
            ->where(fn ($w) => $w->whereNull('effective_from')->orWhereDate('effective_from', '<=', $today))
            ->where(fn ($w) => $w->whereNull('effective_until')->orWhereDate('effective_until', '>=', $today));
    }
}
