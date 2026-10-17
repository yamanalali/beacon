<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'enabled', 'is_default'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'is_default' => 'boolean'];
    }
}
