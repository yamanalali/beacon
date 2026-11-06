<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Enterprise;
use Illuminate\Database\Seeder;

/** One complete demo enterprise owned by the SME user, with the advisor assigned. */
class DemoEnterpriseSeeder extends Seeder
{
    public const NAME = 'ورشة الياسمين للخياطة (تجريبي)';

    public function run(): void
    {
        $enterprise = Enterprise::query()->firstOrCreate(['business_name' => self::NAME], [
            'sector' => 'خياطة وألبسة',
            'location' => 'damascus',
            'founding_date' => '2019-03-01',
            'employee_count' => 6,
            'female_employee_count' => 4,
            'male_employee_count' => 2,
            'owner_name' => 'سلمى الأحمد',
            'contact_name' => 'سلمى الأحمد',
            'contact_phone' => '0930000000',
            'contact_email' => 'sme@beacon.test',
            'registration_status' => 'غير مسجلة رسمياً',
            'initial_digital_readiness_note' => 'تستخدم الهاتف الذكي وواتساب للتواصل مع الزبائن.',
            'initial_financial_readiness_note' => 'السجلات المالية ورقية وغير منتظمة.',
            'programme_stage' => 'التسجيل الأولي',
            'progress_summary' => 'بدأت بتسجيل المبيعات والمصاريف اليومية.',
            'created_by' => DemoUsersSeeder::user(UserRole::BeaconAdmin)->id,
        ]);

        // Keep profile fields filled if an older thin demo row already exists.
        $enterprise->forceFill([
            'sector' => $enterprise->sector ?: 'خياطة وألبسة',
            'location' => $enterprise->location ?: 'damascus',
            'employee_count' => $enterprise->employee_count ?: 6,
            'female_employee_count' => $enterprise->female_employee_count ?: 4,
            'male_employee_count' => $enterprise->male_employee_count ?: 2,
            'owner_name' => $enterprise->owner_name ?: 'سلمى الأحمد',
            'contact_name' => $enterprise->contact_name ?: 'سلمى الأحمد',
            'contact_phone' => $enterprise->contact_phone ?: '0930000000',
            'contact_email' => $enterprise->contact_email ?: 'sme@beacon.test',
            'registration_status' => $enterprise->registration_status ?: 'غير مسجلة رسمياً',
            'initial_digital_readiness_note' => $enterprise->initial_digital_readiness_note
                ?: 'تستخدم الهاتف الذكي وواتساب للتواصل مع الزبائن.',
            'initial_financial_readiness_note' => $enterprise->initial_financial_readiness_note
                ?: 'السجلات المالية ورقية وغير منتظمة.',
            'programme_stage' => $enterprise->programme_stage ?: 'بناء السجلات المالية',
            'progress_summary' => $enterprise->progress_summary
                ?: 'تسجّل المبيعات والمصاريف يومياً، وتجهّز مستندات الامتثال الأساسية.',
        ])->save();

        $enterprise->members()->syncWithoutDetaching([DemoUsersSeeder::user(UserRole::SmeUser)->id]);
        $enterprise->advisors()->syncWithoutDetaching([DemoUsersSeeder::user(UserRole::BeaconAdvisor)->id]);
    }

    public static function enterprise(): Enterprise
    {
        return Enterprise::query()->where('business_name', self::NAME)->firstOrFail();
    }
}
