<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionCategory extends Model
{
    /**
     * Only admin-managed fields; set from validated data.
     *
     * @var list<string>
     */
    protected $fillable = ['key', 'label_ar', 'label_en', 'type_scope', 'is_active', 'sort'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('is_active', true);
    }
}
