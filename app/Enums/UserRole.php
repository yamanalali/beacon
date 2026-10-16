<?php

namespace App\Enums;

enum UserRole: string
{
    case SmeUser = 'sme_user';
    case BeaconAdvisor = 'beacon_advisor';
    case BeaconAdmin = 'beacon_admin';
    case MfiUser = 'mfi_user';
    case TechnicalAdmin = 'technical_admin';

    public function label(): string
    {
        return match ($this) {
            self::SmeUser => 'مستخدم منشأة',
            self::BeaconAdvisor => 'مستشار بيكون',
            self::BeaconAdmin => 'مدير بيكون',
            self::MfiUser => 'مستخدم مؤسسة تمويل',
            self::TechnicalAdmin => 'مدير تقني',
        };
    }
}
