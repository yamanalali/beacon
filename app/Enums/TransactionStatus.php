<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Posted = 'posted';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Posted => 'معتمدة',
            self::Superseded => 'تم تصحيحها',
        };
    }
}
