<?php

namespace App\Models;

use App\Enums\BaselineProfileSection;
use Illuminate\Database\Eloquent\Model;

class BaselineProfileField extends Model
{
    protected $fillable = [
        'key',
        'excel_column',
        'label_ar',
        'section',
        'input_kind',
        'is_visible',
        'is_child',
        'is_system',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'section' => BaselineProfileSection::class,
            'is_visible' => 'boolean',
            'is_child' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
