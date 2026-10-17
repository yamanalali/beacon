<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Posted = 'posted';
    case PendingApproval = 'pending_approval';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Posted => 'معتمدة',
            self::PendingApproval => 'بانتظار الموافقة',
            self::Superseded => 'تم تصحيحها',
        };
    }
}
