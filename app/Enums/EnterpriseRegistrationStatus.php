<?php

namespace App\Enums;

/**
 * Formalization / registration status for an SME profile.
 *
 * TODO: BUSINESS DECISION REQUIRED — confirm final labels and whether
 * Beacon needs additional statuses beyond this starter set.
 */
enum EnterpriseRegistrationStatus: string
{
    case Unregistered = 'unregistered';
    case InProgress = 'in_progress';
    case Partial = 'partial';
    case Registered = 'registered';

    public function label(): string
    {
        return match ($this) {
            self::Unregistered => 'غير مسجّلة',
            self::InProgress => 'قيد التسجيل',
            self::Partial => 'مسجّلة جزئياً',
            self::Registered => 'مسجّلة',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Unregistered => 'لا يوجد سجل أو ترخيص ساري حتى الآن',
            self::InProgress => 'بدأت إجراءات التسجيل ولم تكتمل',
            self::Partial => 'بعض الوثائق أو التسجيلات موجودة فقط',
            self::Registered => 'التسجيل الأساسي مكتمل قدر الإمكان',
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
            'غير مسجلة', 'غير مسجّلة', 'غير مسجلة رسمياً', 'غير مسجّلة رسمياً' => self::Unregistered,
            'قيد التسجيل', 'تسجيل أولي', 'التسجيل الأولي' => self::InProgress,
            'مسجلة جزئياً', 'مسجّلة جزئياً', 'جزئي' => self::Partial,
            'مسجلة', 'مسجّلة', 'مكتمل', 'مكتملة' => self::Registered,
            default => null,
        };
    }
}
