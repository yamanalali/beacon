<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'context',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes = [], array $options = [])
    {
        throw new LogicException('Audit history cannot be edited.');
    }

    public function save(array $options = [])
    {
        if ($this->exists) {
            throw new LogicException('Audit history cannot be edited.');
        }

        return parent::save($options);
    }
}
