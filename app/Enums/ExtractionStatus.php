<?php

namespace App\Enums;

enum ExtractionStatus: string
{
    case PendingReview = 'pending_review';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingReview => 'بانتظار مراجعتك',
            self::Confirmed => 'تم التأكيد',
            self::Rejected => 'مرفوض',
        };
    }
}
