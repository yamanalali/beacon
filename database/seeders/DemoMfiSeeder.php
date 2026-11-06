<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\MfiInstitution;
use App\Models\Referral;
use App\Models\ReferralConsent;
use Illuminate\Database\Seeder;

/**
 * Demo MFI institution, links the demo MFI user to it, and adds one shared referral
 * with a valid consent so /mfi/referrals has something to show. LOCAL only (via DemoSeeder). Idempotent.
 */
class DemoMfiSeeder extends Seeder
{
    public const NAME = 'مؤسسة التمويل الأصغر (تجريبي)';

    public const PURPOSE = 'إحالة تجريبية لعرض صندوق الإحالات لدى مؤسسة التمويل.';

    public function run(): void
    {
        $admin = DemoUsersSeeder::user(UserRole::BeaconAdmin);

        $institution = MfiInstitution::query()->firstOrCreate(['name_ar' => self::NAME], [
            'created_by' => $admin->id,
            'is_active' => true,
        ]);

        $institution->fill([
            'code' => 'MFI-DEMO',
            'name_en' => 'Beacon Demo Microfinance Institution',
            'contact_name' => 'مكتب الإحالات التجريبي',
            'contact_phone' => '+963 11 000 0000',
            'contact_email' => 'mfi@beacon.test',
            'region_ar' => 'دمشق وريف دمشق',
            'address_ar' => 'دمشق — بيانات تجريبية للتطوير المحلي فقط',
            'website' => null,
            'focus_ar' => 'تمويل رأس مال عامل للمنشآت الصغيرة والمتناهية الصغر، مع تركيز تجريبي على الحرف والخدمات.',
            'loan_size_min' => 500000,
            'loan_size_max' => 15000000,
            'loan_currency' => 'SYP',
            'notes' => 'بيانات تجريبية للتطوير المحلي فقط. لا تمثّل مؤسسة تمويل حقيقية.',
            'is_active' => true,
            'created_by' => $institution->created_by ?? $admin->id,
        ])->save();

        if (! $institution->eligibilityCriteria()->exists()) {
            $institution->eligibilityCriteria()->create([
                'label_ar' => 'حد أدنى لدرجة الجاهزية (تجريبي)',
                'type' => 'min_readiness_score',
                'parameters' => ['min_score' => 50],
                'created_by' => $admin->id,
            ]);
            $institution->eligibilityCriteria()->create([
                'label_ar' => 'مراجعة ميدانية من المؤسسة (تجريبي)',
                'type' => 'manual_review',
                'parameters' => null,
                'created_by' => $admin->id,
            ]);
        }

        DemoUsersSeeder::user(UserRole::MfiUser)->forceFill(['mfi_institution_id' => $institution->id])->save();

        $enterprise = DemoEnterpriseSeeder::enterprise();
        $items = array_keys((array) config('readiness.disclosable_items'));

        $referral = Referral::query()->firstOrCreate([
            'enterprise_id' => $enterprise->id,
            'mfi_institution_id' => $institution->id,
            'purpose_ar' => self::PURPOSE,
        ], [
            'status' => Referral::STATUS_SHARED,
            'disclosed_items' => $items,
            'created_by' => $admin->id,
            'shared_at' => now(),
        ]);

        if ($referral->activeConsent() === null) {
            $referral->consents()->create([
                'enterprise_id' => $enterprise->id,
                'mfi_institution_id' => $institution->id,
                'purpose_ar' => self::PURPOSE,
                'consent_version' => (string) config('readiness.consent.version'),
                'consent_text_ar' => (string) config('readiness.consent.text_ar'),
                'scope' => $referral->disclosed_items,
                'status' => ReferralConsent::STATUS_GRANTED,
                'granted_by' => DemoUsersSeeder::user(UserRole::SmeUser)->id,
                'granted_at' => now(),
            ]);
        }

        if (! $referral->statusChanges()->exists()) {
            $referral->statusChanges()->create(['from_status' => Referral::STATUS_DRAFT, 'to_status' => Referral::STATUS_SHARED, 'actor_id' => $admin->id]);
        }
    }
}
