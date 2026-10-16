<?php

namespace App\Enums;

enum AdvisoryRecordStatus: string
{
    case Open = 'open';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'مفتوحة',
            self::Completed => 'مكتملة',
        };
    }
}
