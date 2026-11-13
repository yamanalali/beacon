<?php

namespace App\Enums;

enum BaselineProfileSection: string
{
    case Identity = 'identity';
    case Interest = 'interest';
    case Eligibility = 'eligibility';
    case OwnerWorkforce = 'owner_workforce';
    case ActivityMarket = 'activity_market';
    case Formalization = 'formalization';
    case Finance = 'finance';
    case Digital = 'digital';
    case Financing = 'financing';
    case TrainingSupport = 'training_support';
    case Visit = 'visit';
    case Ops = 'ops';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Identity => 'الهوية والتواصل',
            self::Interest => 'الاهتمام بالمشاركة',
            self::Eligibility => 'الأهلية',
            self::OwnerWorkforce => 'المالك والعمالة',
            self::ActivityMarket => 'النشاط والسوق',
            self::Formalization => 'التسجيل والامتثال',
            self::Finance => 'الإدارة المالية',
            self::Digital => 'الجاهزية الرقمية',
            self::Financing => 'التمويل',
            self::TrainingSupport => 'التدريب والدعم',
            self::Visit => 'ملاحظات الزيارة',
            self::Ops => 'بيانات التشغيل',
            self::Other => 'حقول أخرى',
        };
    }

    public function visibleByDefault(): bool
    {
        return ! in_array($this, [self::Eligibility, self::Visit, self::Ops], true);
    }
}
