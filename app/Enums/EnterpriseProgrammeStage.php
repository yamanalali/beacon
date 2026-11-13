<?php

namespace App\Enums;

/**
 * Programme journey stage for an SME.
 *
 * TODO: BUSINESS DECISION REQUIRED — confirm stage names/order with Beacon.
 * Includes baseline eligibility codes already written by Baseline import.
 */
enum EnterpriseProgrammeStage: string
{
    case Onboarding = 'onboarding';
    case Baseline = 'baseline';
    case BaselineEligible = 'baseline_eligible';
    case BaselineIneligible = 'baseline_ineligible';
    case Records = 'records';
    case Compliance = 'compliance';
    case Readiness = 'readiness';
    case Referral = 'referral';
    case ActiveSupport = 'active_support';

    public function label(): string
    {
        return match ($this) {
            self::Onboarding => 'التسجيل الأولي',
            self::Baseline => 'تقييم Baseline',
            self::BaselineEligible => 'مؤهلة بعد Baseline',
            self::BaselineIneligible => 'غير مؤهلة بعد Baseline',
            self::Records => 'بناء السجلات المالية',
            self::Compliance => 'الامتثال والوثائق',
            self::Readiness => 'الجاهزية للإحالة',
            self::Referral => 'الإحالة التمويلية',
            self::ActiveSupport => 'متابعة ودعم',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Onboarding => 'المنشأة دخلت البرنامج وملء الملف الأساسي',
            self::Baseline => 'استبيان Baseline قيد الاستكمال أو المراجعة',
            self::BaselineEligible => 'اجتازت فحوصات أهلية Baseline',
            self::BaselineIneligible => 'لم تستوفِ شروط الأهلية الحالية',
            self::Records => 'التركيز على المعاملات والسجلات المالية',
            self::Compliance => 'استكمال المتطلبات والوثائق النظامية',
            self::Readiness => 'التحضير لملف الإحالة والتمويل',
            self::Referral => 'إحالة فعّالة أو قيد المتابعة مع جهة تمويل',
            self::ActiveSupport => 'دعم استشاري مستمر بعد التقدم',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function tryFromMixed(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $direct = self::tryFrom($value);
        if ($direct) {
            return $direct;
        }

        $normalized = trim(mb_strtolower($value));

        return match ($normalized) {
            'التسجيل الأولي', 'تسجيل أولي' => self::Onboarding,
            'تقييم baseline', 'baseline' => self::Baseline,
            'بناء السجلات المالية', 'السجلات المالية' => self::Records,
            'الامتثال والوثائق', 'الامتثال' => self::Compliance,
            'الجاهزية للإحالة', 'الجاهزية' => self::Readiness,
            'الإحالة التمويلية', 'الإحالة' => self::Referral,
            'متابعة ودعم', 'متابعة' => self::ActiveSupport,
            default => null,
        };
    }
}
