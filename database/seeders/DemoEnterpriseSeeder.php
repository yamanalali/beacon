<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Enterprise;
use Illuminate\Database\Seeder;

/**
 * Demo enterprises for local/staging showcase.
 * Primary SME is owned by sme@beacon.test; additional SMEs fill the advisor portfolio.
 */
class DemoEnterpriseSeeder extends Seeder
{
    public const NAME = 'ورشة الياسمين للخياطة (تجريبي)';

    /**
     * Extra portfolio enterprises (advisor-facing). Idempotent by business_name.
     *
     * @var list<array<string, mixed>>
     */
    public const ADDITIONAL = [
        [
            'business_name' => 'مخبز أبو خالد (تجريبي)',
            'sector' => 'أغذية ومخابز',
            'location' => 'rural_damascus',
            'founding_date' => '2016-08-15',
            'employee_count' => 4,
            'female_employee_count' => 1,
            'male_employee_count' => 3,
            'owner_name' => 'خالد الحسن',
            'contact_name' => 'خالد الحسن',
            'contact_phone' => '0941111001',
            'contact_email' => 'bakery-demo@beacon.test',
            'registration_status' => 'partial',
            'initial_digital_readiness_note' => 'يستخدم واتساب لاستقبال الطلبات فقط.',
            'initial_financial_readiness_note' => 'دفتر يومية بسيط بدون تصنيفات.',
            'programme_stage' => 'records',
            'progress_summary' => 'بدأ بتسجيل المبيعات اليومية ويحتاج تنظيم المصاريف.',
        ],
        [
            'business_name' => 'صالون نور للتجميل (تجريبي)',
            'sector' => 'خدمات تجميل',
            'location' => 'damascus',
            'founding_date' => '2021-02-10',
            'employee_count' => 3,
            'female_employee_count' => 3,
            'male_employee_count' => 0,
            'owner_name' => 'نور محمود',
            'contact_name' => 'نور محمود',
            'contact_phone' => '0941111002',
            'contact_email' => 'salon-demo@beacon.test',
            'registration_status' => 'unregistered',
            'initial_digital_readiness_note' => 'حجز عبر الهاتف وإنستغرام.',
            'initial_financial_readiness_note' => 'إيرادات نقدية دون فواتير منتظمة.',
            'programme_stage' => 'onboarding',
            'progress_summary' => 'أكملت الملف الأساسي وتنتظر جلسة امتثال.',
        ],
        [
            'business_name' => 'ورشة الأمل للنجارة (تجريبي)',
            'sector' => 'نجارة وأثاث',
            'location' => 'daraa',
            'founding_date' => '2014-05-20',
            'employee_count' => 8,
            'female_employee_count' => 0,
            'male_employee_count' => 8,
            'owner_name' => 'أحمد الصفدي',
            'contact_name' => 'أحمد الصفدي',
            'contact_phone' => '0941111003',
            'contact_email' => 'carpentry-demo@beacon.test',
            'registration_status' => 'registered',
            'initial_digital_readiness_note' => 'يتواصل مع الزبائن عبر الهاتف فقط.',
            'initial_financial_readiness_note' => 'سجلات مشتريات جزئية؛ المبيعات غير مكتملة.',
            'programme_stage' => 'readiness',
            'progress_summary' => 'تحسّن التسجيل المالي ويستعد لمطابقة مؤسسات التمويل.',
        ],
        [
            'business_name' => 'متجر الخير للبقالة (تجريبي)',
            'sector' => 'تجارة تجزئة',
            'location' => 'damascus',
            'founding_date' => '2018-11-01',
            'employee_count' => 2,
            'female_employee_count' => 1,
            'male_employee_count' => 1,
            'owner_name' => 'فاطمة درويش',
            'contact_name' => 'فاطمة درويش',
            'contact_phone' => '0941111004',
            'contact_email' => 'grocery-demo@beacon.test',
            'registration_status' => 'unregistered',
            'initial_digital_readiness_note' => 'تبيع عبر واتساب للمحلة.',
            'initial_financial_readiness_note' => 'لا يوجد فصل واضح بين مصروف البيت والمحل.',
            'programme_stage' => 'records',
            'progress_summary' => 'سجّلت أول أسبوع من المبيعات وتحتاج متابعة مستشار.',
        ],
        [
            'business_name' => 'مزرعة الربيع للمنتجات الزراعية (تجريبي)',
            'sector' => 'زراعة',
            'location' => 'rural_damascus',
            'founding_date' => '2012-04-01',
            'employee_count' => 12,
            'female_employee_count' => 5,
            'male_employee_count' => 7,
            'owner_name' => 'سامر العلي',
            'contact_name' => 'سامر العلي',
            'contact_phone' => '0941111005',
            'contact_email' => 'farm-demo@beacon.test',
            'registration_status' => 'partial',
            'initial_digital_readiness_note' => 'صوراً للمنتجات عبر الهاتف؛ لا نظام حجوزات.',
            'initial_financial_readiness_note' => 'موسمية الإيرادات؛ يحتاج تقارير بسيطة.',
            'programme_stage' => 'compliance',
            'progress_summary' => 'يعمل على استكمال وثائق الترخيص والتراخيص الزراعية.',
        ],
    ];

    public function run(): void
    {
        $adminId = DemoUsersSeeder::user(UserRole::BeaconAdmin)->id;
        $advisorId = DemoUsersSeeder::user(UserRole::BeaconAdvisor)->id;
        $smeId = DemoUsersSeeder::user(UserRole::SmeUser)->id;

        $primary = $this->upsertEnterprise([
            'business_name' => self::NAME,
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
            'registration_status' => 'unregistered',
            'initial_digital_readiness_note' => 'تستخدم الهاتف الذكي وواتساب للتواصل مع الزبائن.',
            'initial_financial_readiness_note' => 'السجلات المالية ورقية وغير منتظمة.',
            'programme_stage' => 'records',
            'progress_summary' => 'تسجّل المبيعات والمصاريف يومياً، وتجهّز مستندات الامتثال الأساسية.',
        ], $adminId);

        $primary->members()->syncWithoutDetaching([$smeId]);
        $primary->advisors()->syncWithoutDetaching([$advisorId]);

        foreach (self::ADDITIONAL as $row) {
            $enterprise = $this->upsertEnterprise($row, $adminId);
            $enterprise->advisors()->syncWithoutDetaching([$advisorId]);
        }
    }

    /** Assign the demo advisor to every enterprise (showcase / advisor workflows). */
    public static function assignDemoAdvisorToAll(): void
    {
        $advisorId = DemoUsersSeeder::user(UserRole::BeaconAdvisor)->id;
        Enterprise::query()->each(function (Enterprise $row) use ($advisorId): void {
            $row->advisors()->syncWithoutDetaching([$advisorId]);
        });
    }

    public static function enterprise(): Enterprise
    {
        return Enterprise::query()->where('business_name', self::NAME)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function upsertEnterprise(array $attrs, int $adminId): Enterprise
    {
        $enterprise = Enterprise::query()->firstOrCreate(
            ['business_name' => $attrs['business_name']],
            $attrs + ['created_by' => $adminId],
        );

        $fill = [];
        foreach ($attrs as $key => $value) {
            if ($key === 'business_name') {
                continue;
            }
            if ($enterprise->{$key} === null || $enterprise->{$key} === '') {
                $fill[$key] = $value;
            }
        }
        if ($fill !== []) {
            $enterprise->forceFill($fill)->save();
        }

        return $enterprise;
    }
}
