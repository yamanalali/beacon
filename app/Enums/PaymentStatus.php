<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Paid = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Unpaid = 'unpaid';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'مدفوع بالكامل',
            self::PartiallyPaid => 'مدفوع جزئياً',
            self::Unpaid => 'غير مدفوع (آجل)',
        };
    }
}
